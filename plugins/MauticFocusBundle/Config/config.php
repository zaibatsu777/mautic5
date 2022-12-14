<?php

return [
    'name'        => 'Mautic Focus',
    'description' => 'Drive visitor\'s focus on your website with Mautic Focus',
    'version'     => '1.0',
    'author'      => 'Mautic, Inc',

    'routes' => [
        'main' => [
            'mautic_focus_index' => [
                'path'       => '/focus/{page}',
                'controller' => 'MauticPlugin\MauticFocusBundle\Controller\FocusController::indexAction',
            ],
            'mautic_focus_action' => [
                'path'       => '/focus/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\MauticFocusBundle\Controller\FocusController::executeAction',
            ],
        ],
        'public' => [
            'mautic_focus_generate' => [
                'path'       => '/focus/{id}.js',
                'controller' => 'MauticPlugin\MauticFocusBundle\Controller\PublicController::generateAction',
            ],
            'mautic_focus_pixel' => [
                'path'       => '/focus/{id}/viewpixel.gif',
                'controller' => 'MauticPlugin\MauticFocusBundle\Controller\PublicController::viewPixelAction',
            ],
        ],
        'api' => [
            'mautic_api_focusstandard' => [
                'standard_entity' => true,
                'name'            => 'focus',
                'path'            => '/focus',
                'controller'      => 'MauticPlugin\MauticFocusBundle\Controller\Api\FocusApiController',
            ],
            'mautic_api_focusjs' => [
                'path'       => '/focus/{id}/js',
                'controller' => 'MauticPlugin\MauticFocusBundle\Controller\Api\FocusApiController::generateJsAction',
                'method'     => 'POST',
            ],
        ],
    ],

    'services' => [
        'events' => [
            'mautic.focus.subscriber.form_bundle' => [
                'class'     => \MauticPlugin\MauticFocusBundle\EventListener\FormSubscriber::class,
                'arguments' => [
                    'mautic.focus.model.focus',
                ],
            ],
            'mautic.focus.subscriber.page_bundle' => [
                'class'     => \MauticPlugin\MauticFocusBundle\EventListener\PageSubscriber::class,
                'arguments' => [
                    'mautic.security',
                    'mautic.focus.model.focus',
                    'router',
                    'mautic.helper.token_builder.factory',
                ],
            ],
            'mautic.focus.subscriber.stat' => [
                'class'     => \MauticPlugin\MauticFocusBundle\EventListener\StatSubscriber::class,
                'arguments' => [
                    'mautic.focus.model.focus',
                    'request_stack',
                ],
            ],
            'mautic.focus.subscriber.focus' => [
                'class'     => \MauticPlugin\MauticFocusBundle\EventListener\FocusSubscriber::class,
                'arguments' => [
                    'router',
                    'mautic.helper.ip_lookup',
                    'mautic.core.model.auditlog',
                    'mautic.page.model.trackable',
                    'mautic.page.helper.token',
                    'mautic.asset.helper.token',
                    'mautic.focus.model.focus',
                    'request_stack',
                ],
            ],
            'mautic.focus.stats.subscriber' => [
                'class'     => \MauticPlugin\MauticFocusBundle\EventListener\StatsSubscriber::class,
                'arguments' => [
                    'mautic.security',
                    'doctrine.orm.entity_manager',
                ],
            ],
            'mautic.focus.campaignbundle.subscriber' => [
                'class'     => \MauticPlugin\MauticFocusBundle\EventListener\CampaignSubscriber::class,
                'arguments' => [
                    'mautic.page.helper.tracking',
                    'router',
                ],
            ],
        ],
        'forms' => [
            'mautic.focus.form.type.focus' => [
                'class'     => \MauticPlugin\MauticFocusBundle\Form\Type\FocusType::class,
                'arguments' => 'mautic.security',
            ],
            'mautic.focus.form.type.focusshow_list' => [
                'class'     => \MauticPlugin\MauticFocusBundle\Form\Type\FocusShowType::class,
                'arguments' => 'router',
            ],
            'mautic.focus.form.type.focus_list' => [
                'class'     => \MauticPlugin\MauticFocusBundle\Form\Type\FocusListType::class,
                'arguments' => 'mautic.focus.model.focus',
            ],
        ],
        'models' => [
            'mautic.focus.model.focus' => [
                'class'     => \MauticPlugin\MauticFocusBundle\Model\FocusModel::class,
                'arguments' => [
                    'mautic.form.model.form',
                    'mautic.page.model.trackable',
                    'mautic.helper.templating',
                    'event_dispatcher',
                    'mautic.lead.model.field',
                    'mautic.tracker.contact',
                ],
            ],
        ],
        'other' => [
            'mautic.focus.helper.token' => [
                'class'     => \MauticPlugin\MauticFocusBundle\Helper\TokenHelper::class,
                'arguments' => [
                    'mautic.focus.model.focus',
                    'router',
                    'mautic.security',
                ],
            ],
            'mautic.focus.helper.iframe_availability_checker' => [
                'class'     => \MauticPlugin\MauticFocusBundle\Helper\IframeAvailabilityChecker::class,
                'arguments' => [
                    'translator',
                ],
            ],
        ],
    ],

    'menu' => [
        'main' => [
            'mautic.focus' => [
                'route'    => 'mautic_focus_index',
                'access'   => 'focus:items:view',
                'parent'   => 'mautic.core.channels',
                'priority' => 10,
            ],
        ],
    ],

    'categories' => [
        'plugin:focus' => 'mautic.focus',
    ],

    'parameters' => [
        'website_snapshot_url' => 'https://mautic.net/api/snapshot',
        'website_snapshot_key' => '',
    ],
];
