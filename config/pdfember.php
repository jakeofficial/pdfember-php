<?php

return [

    /*
     * Your API key (`pdx_live_...`), from https://pdfember.com/dashboard.
     */
    'api_key' => env('PDFEMBER_API_KEY'),

    /*
     * Change only for a self-hosted pdfember or a test server.
     */
    'base_url' => env('PDFEMBER_BASE_URL', 'https://pdfember.com/api/v1'),

    /*
     * Seconds to wait for a PDF. A render may take up to 60.
     */
    'timeout' => (float) env('PDFEMBER_TIMEOUT', 70),

];
