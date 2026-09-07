<?php

return [
    'temporary_file_upload' => [
        'rules' => ['required', 'file', 'max:768000'],
        'max_upload_time' => 30,
    ],
];
