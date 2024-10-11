<?php

return [
    
    "routes" => [
		
        "gateway_user.gateway.sms"                  => "Oops! Your current Plan doesn't allow you to add SMS Gateways.",
        "campaign_user.communication.sms.campaign"  => "Oops! Your current Plan doesn't allow you to maintain SMS Campaign.",
        "message_user.communication.sms"            => "Oops! Your current Plan doesn't allow you to use SMS Message.",

        "gateway_user.gateway.whatsapp"             => "Oops! Your current Plan doesn't allow you to add Whatsapp Accounts.",
        "gateway_user.gateway.whatsapp.device"                => "Oops! Your current Plan doesn't allow you to add Whatsapp Devices.",
        "gateway_user.gateway.whatsapp.device.server.qrcode"  => "Oops! Your current Plan doesn't allow you to add Whatsapp Devices.",
        "gateway_user.gateway.whatsapp.device.server.status"  => "Oops! Your current Plan doesn't allow you to add Whatsapp Devices.",
        "campaign_user.communication.whatsapp.campaign"       => "Oops! Your current Plan doesn't allow you to maintain Whatsapp Campaign.",
        "message_user.communication.whatsapp"                 => "Oops! Your current Plan doesn't allow you to use Whatsapp message.",
        
        
        "gateway_user.gateway.email"                 => "Oops! Your current Plan doesn't allow you to add Email Gateways.",
        "campaign_user.communication.email.campaign" => "Oops! Your current Plan doesn't allow you to maintain Email Campaign.",
        "message_user.communication.email"           => "Oops! Your current Plan doesn't allow you to use Email Message.",

        "gateway_sendmethod.android"              => "Oops! Your current Plan doesn't allow you to add Android Gateway.",
        "settings_user.gateway.sms.android"       => "Oops! Your current Plan doesn't allow you to send messages with an Android gateway.",

    ],

    "types" => [
        "whatsapp",
        "sms",
        "email",
        "android"
    ],

    "gateway_access" => [
        'user_create',
        'admin_access'
    ],

    "pricing_plan" => [

        "sms" => [
            "is_allowed" => true,
            "gateway_limit" => 0,
            "allowed_gateways" => [

            ],
            "credits" => "sms_credit",
            "credits_per_day" => "sms_credit_per_day",
            "android" => [
                "is_allowed"    => true,
                "gateway_limit" => 0
            ]
        ],
        "email" => [
            "is_allowed" => true,
            "gateway_limit" => 0,
            "allowed_gateways" => [

            ],
            "credits" => "email_credit",
            "credits_per_day" => "email_credit_per_day"
        ],
        "whatsapp" => [
            "is_allowed" => true,
            "gateway_limit" => 0,
            "credits" => "whatsapp_credit",
            "credits_per_day" => "whatsapp_credit_per_day"
        ],
    ]
];
