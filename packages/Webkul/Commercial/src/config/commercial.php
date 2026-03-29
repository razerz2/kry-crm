<?php

return [
    'campaign' => [
        /*
         | Email provider key stored in each delivery row.
         | Possible values: internal_email
         | Future: mailgun, ses, sendgrid, etc.
         */
        'email_provider' => env('COMMERCIAL_EMAIL_PROVIDER', 'internal_email'),

        /*
         | WhatsApp provider key stored in each delivery row.
         | Possible values: waha | evolution | meta_official
         | No real provider is wired yet — deliveries will be marked failed with a clear reason.
         */
        'whatsapp_provider' => env('COMMERCIAL_WHATSAPP_PROVIDER', 'waha'),

        /*
         | Laravel queue name used for campaign dispatch jobs.
         | Set COMMERCIAL_CAMPAIGN_QUEUE=campaigns to isolate campaign traffic.
         */
        'queue' => env('COMMERCIAL_CAMPAIGN_QUEUE', 'default'),

        /*
         | Number of audience / delivery rows processed per chunk in bulk operations.
         */
        'delivery_chunk_size' => (int) env('COMMERCIAL_DELIVERY_CHUNK_SIZE', 200),
    ],
];
