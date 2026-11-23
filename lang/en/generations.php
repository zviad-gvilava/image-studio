<?php

return [

    'provider_unavailable' => "We couldn't reach the image service just now. Nothing was charged. Please try again in a moment.",

    /*
    | Why a generation failed, keyed by error code. Failed generations are never charged.
    */
    'failed' => [
        'content_policy_violation' => 'This prompt was blocked by the content policy. Try rewording it.',
        'generation_failed' => "The model couldn't create an image for this prompt. Try rewording it.",
        'internal_error' => 'Something went wrong while generating. Please try again.',
        'overloaded' => 'The image service was overloaded. Please try again in a minute.',
        'rate_limit_exceeded' => 'The image service stayed busy for too long. Please try again.',
        'concurrency_limit_exceeded' => 'The image service stayed busy for too long. Please try again.',
        'daily_limit_exceeded' => "Today's generation limit has been reached. It resets at midnight UTC.",
        'connection_failed' => "We couldn't reach the image service. Please try again.",
        'unknown' => 'Something went wrong on our side. Please try again later.',
    ],

    /*
    | What a waiting generation is waiting for, keyed by the error that delayed it.
    */
    'retrying' => [
        'concurrency_limit_exceeded' => 'Waiting for a free slot: the image service runs a few generations at a time.',
        'rate_limit_exceeded' => 'Too many requests right now. Retrying shortly.',
        'overloaded' => 'The image service is overloaded. Retrying automatically.',
        'connection_failed' => "Couldn't reach the image service. Retrying automatically.",
        'unknown' => 'The image service hiccuped. Retrying automatically.',
    ],

];
