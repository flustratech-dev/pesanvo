<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | used by your application. An example configuration is provided for
    | each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis",
    |          "deferred", "background", "failover", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            /*
            | HARUS lebih besar dari `$timeout` job terpanjang, dan dari
            | `--timeout` milik `queue:work` di start.sh (240).
            |
            | Nilai ini mengukur berapa lama sebuah job boleh dianggap masih
            | berjalan sebelum antrean menyimpulkan worker-nya mati dan
            | melepasnya untuk dikerjakan ulang. Bawaan Laravel 90 detik, dan
            | itu LEBIH PENDEK daripada hampir seluruh job di sini
            | (SusunEksporDataJob 900, PruneOldRecordsJob 600, BillingCycleJob
            | 300, SendMessageJob 120). Akibatnya bukan job yang gagal
            | melainkan job yang dikerjakan DUA KALI sementara yang pertama
            | masih berjalan.
            |
            | Untuk SendMessageJob artinya penerima menerima pesan yang sama
            | dua kali — kegagalan yang sama persis dengan yang ditutup map
            | `#kiriman` di engine (session-manager.js), kecuali map itu cuma
            | menyimpan 15 menit, jadi percobaan ulang di luar jendela itu
            | benar-benar mengirim ulang.
            |
            | 1200 dan bukan 900: SusunEksporDataJob bertimeout tepat 900, dan
            | sama besar tetap balapan. Kalau ada job baru dengan timeout lebih
            | panjang dari 1200, angka ini yang harus naik lebih dulu.
            */
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 1200),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            // Disamakan dengan connection `database` di atas; alasannya di sana.
            // Dijaga tests/Unit/AntreanTest.php.
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 1200),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            // Disamakan dengan connection `database` di atas; alasannya di sana.
            // Pindah ke Redis suatu saat tidak boleh menghidupkan lagi bug pesan
            // ganda hanya karena angkanya tertinggal di connection yang lain.
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 1200),
            'block_for' => null,
            'after_commit' => false,
        ],

        'deferred' => [
            'driver' => 'deferred',
        ],

        'background' => [
            'driver' => 'background',
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'database',
                'deferred',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored. Laravel ships with
    | support for storing failed jobs in a simple file or in a database.
    |
    | Supported drivers: "database-uuids", "dynamodb", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

];
