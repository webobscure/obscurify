<?php

return [

    'verify_email' => [
        'subject' => 'Verify your email — :store',
        'body' => 'Use this token to verify your email address.',
        'ignore' => "If you didn't create an account, you can safely ignore this email.",
    ],

    'password_reset' => [
        'subject' => 'Reset your password — :store',
        'body' => 'Use this token to reset your password. It expires shortly and can only be used once.',
        'ignore' => "If you didn't request this, you can safely ignore this email.",
    ],

];
