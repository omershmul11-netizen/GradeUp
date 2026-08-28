<?php

require_once __DIR__ . '/config.php';

if (!defined('OPENAI_API_KEY')) {
    define('OPENAI_API_KEY', gradeup_config('OPENAI_API_KEY', ''));
}
