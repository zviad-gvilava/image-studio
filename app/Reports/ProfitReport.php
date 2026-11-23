<?php

namespace App\Reports;

use App\Billing\Money;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use App\Pixelforge\UsageDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * What users were charged, what Pixelforge charged us, and the difference, per day and per user.
 * Days are the UTC day a generation completed: that is how Pixelforge bills, so each day can be
 * checked against its GET /v1/usage report.
 */
final readonly class ProfitReport
{
    public function __construct(
        private PixelforgeClient $pixelforge,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->rows($from, $to);
        $usage = $this->usage($from, $to);
        $users = $this->users($rows);

        $days = $rows->pluck('day')
            ->merge($usage === null ? [] : $this->billedDates($usage))
            ->unique()
            ->sortDesc()
            ->values()
            ->map(fn (string $date): array => $this->day($date, $rows->where('day', $date), $users, $usage));

        $total = $this->sum($rows);

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => [...$total->toArray(), ...$this->comparison($total, $this->usageTotal($usage))],
            'days' => $days->all(),
            'users' => $rows->groupBy(fn (object $row): int => $row->user_id ?? 0)
                ->map(fn (Collection $userRows, int $userId): array => ['user' => $this->userLabel($users, $userId), 'line' => $this->sum($userRows)])
                ->sortByDesc(fn (array $entry): int => $entry['line']->charged->nanos)
                ->map(fn (array $entry): array => ['user' => $entry['user'], ...$entry['line']->toArray()])
                ->values()
                ->all(),
            'pixelforge_available' => $usage !== null,
        ];
    }

    /**
     * Days Pixelforge billed for, so a day it billed but we have no record of still shows up.
     *
     * @param  array<string, UsageDay>  $usage
     * @return list<string>
     */
    private function billedDates(array $usage): array
    {
        return array_values(array_map(
            fn (UsageDay $day): string => $day->date,
            array_filter($usage, fn (UsageDay $day): bool => $day->generations > 0),
        ));
    }

    /**
     * @param  array<string, UsageDay>|null  $usage
     */
    private function usageTotal(?array $usage): ?UsageDay
    {
        if ($usage === null) {
            return null;
        }

        return array_reduce(
            $usage,
            fn (UsageDay $carry, UsageDay $day): UsageDay => new UsageDay('total', $carry->generations + $day->generations, $carry->cost->plus($day->cost)),
            new UsageDay('total', 0, Money::zero()),
        );
    }

    /**
     * @return Collection<int, object{day: string, user_id: int|null, generations: int, charged: int, provider_cost: int}>
     */
    private function rows(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        /** @var Collection<int, object{day: string, user_id: int|null, generations: int, charged: int, provider_cost: int}> */
        return Generation::query()
            ->toBase()
            ->selectRaw('DATE(completed_at) as day, user_id, COUNT(*) as generations, SUM(price) as charged, SUM(provider_cost) as provider_cost')
            ->where('status', GenerationStatus::Succeeded)
            ->where('completed_at', '>=', $from)
            ->where('completed_at', '<', $to->addDay())
            ->groupByRaw('DATE(completed_at), user_id')
            ->get()
            ->map(fn (object $row): object => (object) [
                'day' => (string) $row->day,
                'user_id' => $row->user_id === null ? null : (int) $row->user_id,
                'generations' => (int) $row->generations,
                'charged' => (int) $row->charged,
                'provider_cost' => (int) $row->provider_cost,
            ]);
    }

    /**
     * Pixelforge's own record of what it billed, keyed by date. Null when it can't be reached.
     *
     * @return array<string, UsageDay>|null
     */
    private function usage(CarbonImmutable $from, CarbonImmutable $to): ?array
    {
        try {
            return collect($this->pixelforge->usage($from, $to))->keyBy('date')->all();
        } catch (PixelforgeException $exception) {
            Log::warning('Could not load the Pixelforge usage report.', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * @param  Collection<int, object{day: string, user_id: int|null, generations: int, charged: int, provider_cost: int}>  $rows
     * @return Collection<int, User>
     */
    private function users(Collection $rows): Collection
    {
        return User::query()
            ->whereIn('id', $rows->pluck('user_id')->filter())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, object{day: string, user_id: int|null, generations: int, charged: int, provider_cost: int}>  $rows
     * @param  Collection<int, User>  $users
     * @param  array<string, UsageDay>|null  $usage
     * @return array<string, mixed>
     */
    private function day(string $date, Collection $rows, Collection $users, ?array $usage): array
    {
        $total = $this->sum($rows);

        return [
            'date' => $date,
            ...$total->toArray(),
            ...$this->comparison($total, $usage === null ? null : ($usage[$date] ?? new UsageDay($date, 0, Money::zero()))),
            'users' => $rows
                ->map(fn (object $row): array => [
                    'user' => $this->userLabel($users, $row->user_id),
                    ...$this->line($row)->toArray(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{pixelforge: array{generations: int, cost: array{exact: string, formatted: string}}|null, matches: bool|null}
     */
    private function comparison(ProfitLine $ours, ?UsageDay $theirs): array
    {
        return [
            'pixelforge' => $theirs === null ? null : ['generations' => $theirs->generations, 'cost' => $theirs->cost->display()],
            'matches' => $theirs === null ? null : $theirs->generations === $ours->generations && $theirs->cost->nanos === $ours->providerCost->nanos,
        ];
    }

    /**
     * @param  Collection<int, object{day: string, user_id: int|null, generations: int, charged: int, provider_cost: int}>  $rows
     */
    private function sum(Collection $rows): ProfitLine
    {
        return $rows->reduce(fn (ProfitLine $carry, object $row): ProfitLine => $carry->plus($this->line($row)), ProfitLine::empty());
    }

    /**
     * @param  object{day: string, user_id: int|null, generations: int, charged: int, provider_cost: int}  $row
     */
    private function line(object $row): ProfitLine
    {
        return new ProfitLine($row->generations, Money::ofNanos($row->charged), Money::ofNanos($row->provider_cost));
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{id: int|null, name: string, email: string|null}
     */
    private function userLabel(Collection $users, ?int $userId): array
    {
        $user = $userId === null ? null : $users->get($userId);

        return [
            'id' => $user?->id,
            'name' => $user->name ?? 'Deleted user',
            'email' => $user?->email,
        ];
    }
}
