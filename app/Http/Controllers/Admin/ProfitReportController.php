<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfitReportRequest;
use App\Reports\ProfitReport;
use Inertia\Inertia;
use Inertia\Response;

final class ProfitReportController extends Controller
{
    public function __invoke(ProfitReportRequest $request, ProfitReport $report): Response
    {
        return Inertia::render('admin/Report', [
            ...$report->build($request->from(), $request->to()),
            'max_days' => ProfitReportRequest::MAX_DAYS,
        ]);
    }
}
