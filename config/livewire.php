<?php

$config = require __DIR__.'/../vendor/livewire/livewire/config/livewire.php';

$config['temporary_file_upload']['disk'] = 'local';
$config['temporary_file_upload']['rules'] = ['file', 'max:2048'];
$config['temporary_file_upload']['directory'] = 'livewire-tmp';
$config['temporary_file_upload']['preview_mimes'][] = 'txt';
$config['temporary_file_upload']['preview_mimes'][] = 'ovpn';
$config['temporary_file_upload']['preview_mimes'][] = 'conf';

return $config;
