<?php

namespace App\Exceptions;

use RuntimeException;

// A timetable rule failed (BR-7.8, 8.x, 9.2). The message is safe to show the admin.
class SessionException extends RuntimeException {}
