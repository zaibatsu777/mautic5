<?php

return [
    'routes' => [
        'main' => [
            'mautic_pointtriggerevent_action' => [
                'path'       => '/points/triggers/events/{objectAction}/{objectId}',
                'controller' => 'Mautic\PointBundle\Controller\TriggerEventController::executeAction',
            ],
            'mautic_pointtrigger_index' => [
                'path'       => '/points/triggers/{page}',
                'controller' => 'Mautic\PointBundle\Controller\TriggerController::indexAction',
            ],
            'mautic_pointtrigger_action' => [
                'path'       => '/points/triggers/{objectAction}/{objectId}',
                'controller' => 'Mautic\PointBundle\Controller\TriggerController::executeAction',
            ],
            'mautic_point_index' => [
                'path'       => '/points/{page}',
                'controller' => 'Mautic\PointBundle\Controller\PointController::indexAction',
            ],
            'mautic_point_action' => [
                'path'       => '/points/{objectAction}/{objectId}',
                'controller' => 'Mautic\PointBundle\Controller\PointController::executeAction',
            ],
        ],
        'api' => [
            'mautic_api_pointactionsstandard' => [
                'standard_entity' => true,
                'name'            => 'points',
                'path'            => '/points',
                'controller'      => 'Mautic\PointBundle\Controller\Api\PointApiController',
            ],
            'mautic_api_getpointactiontypes' => [
                'path'       => '/points/actions/types',
                'controller' => 'Mautic\PointBundle\Controller\Api\PointApiController::getPointActionTypesAction',
            ],
            'mautic_api_pointtriggersstandard' => [
                'standard_entity' => true,
                'name'            => 'triggers',
                'path'            => '/points/triggers',
                'controller'      => 'Mautic\PointBundle\Controller\Api\TriggerApiController',
            ],
            'mautic_api_getpointtriggereventtypes' => [
                'path'       => '/points/triggers/events/types',
                'controller' => 'Mautic\PointBundle\Controller\Api\TriggerApiController::getPointTriggerEventTypesAction',
            ],
            'mautic_api_pointtriggerdeleteevents' => [
                'path'       => '/points/triggers/{triggerId}/events/delete',
                'controller' => 'Mautic\PointBundle\Controller\Api\TriggerApiController::deletePointTriggerEventsAction',
                'method'     => 'DELETE',
            ],
            'mautic_api_adjustcontactpoints' => [
                'path'       => '/contacts/{leadId}/points/{operator}/{delta}',
                'controller' => 'Mautic\PointBundle\Controller\Api\PointApiController::adjustPointsAction',
                'method'     => 'POST',
            ],
        ],
    ],

    'menu' => [
        'main' => [
            'mautic.points.menu.root' => [
                'id'        => 'mautic_points_root',
                'iconClass' => 'fa-calculator',
                'access'    => ['point:points:view', 'point:triggers:view'],
                'priority'  => 30,
                'children'  => [
                    'mautic.point.menu.index' => [
                        'route'  => 'mautic_point_index',
                        'access' => 'point:points:view',
                    ],
                    'mautic.point.trigger.menu.index' => [
                        'route'  => 'mautic_pointtrigger_index',
                        'access' => 'point:triggers:view',
                    ],
                ],
            ],
        ],
    ],

    'categories' => [
        'point' => null,
    ],

    'services' => [
        'events' => [
            'mautic.point.subscriber' => [
                'class'     => \Mautic\PointBundle\EventListener\PointSubscriber::class,
                'arguments' => [
                    'mautic.helper.ip_lookup',
                    'mautic.core.model.auditlog',
                ],
            ],
            'mautic.point.leadbundle.subscriber' => [
                'class'     => \Mautic\PointBundle\EventListener\LeadSubscriber::class,
                'arguments' => [
                    'mautic.point.model.trigger',
                    'translator',
                    'mautic.lead.repository.points_change_log',
                    'mautic.point.repository.lead_point_log',
                    'mautic.point.repository.lead_trigger_log',
                ],
            ],
            'mautic.point.search.subscriber' => [
                'class'     => \Mautic\PointBundle\EventListener\SearchSubscriber::class,
                'arguments' => [
                    'mautic.point.model.point',
                    'mautic.point.model.trigger',
                    'mautic.security',
                    'mautic.helper.templating',
                ],
            ],
            'mautic.point.dashboard.subscriber' => [
                'class'     => \Mautic\PointBundle\EventListener\DashboardSubscriber::class,
                'arguments' => [
                    'mautic.point.model.point',
                ],
            ],
            'mautic.point.stats.subscriber' => [
                'class'     => \Mautic\PointBundle\EventListener\StatsSubscriber::class,
                'arguments' => [
                    'mautic.security',
                    'doctrine.orm.entity_manager',
                ],
            ],
        ],
        'forms' => [
            'mautic.point.type.form' => [
                'class'     => \Mautic\PointBundle\Form\Type\PointType::class,
                'arguments' => ['mautic.security'],
            ],
            'mautic.point.type.action' => [
                'class' => \Mautic\PointBundle\Form\Type\PointActionType::class,
            ],
            'mautic.pointtrigger.type.form' => [
                'class'     => \Mautic\PointBundle\Form\Type\TriggerType::class,
                'arguments' => [
                  'mautic.security',
                ],
            ],
            'mautic.pointtrigger.type.action' => [
                'class' => \Mautic\PointBundle\Form\Type\TriggerEventType::class,
            ],
            'mautic.point.type.genericpoint_settings' => [
                'class' => \Mautic\PointBundle\Form\Type\GenericPointSettingsType::class,
            ],
        ],
        'models' => [
            'mautic.point.model.point' => [
                'class'     => \Mautic\PointBundle\Model\PointModel::class,
                'arguments' => [
                    'session',
                    'mautic.helper.ip_lookup',
                    'mautic.lead.model.lead',
                    'mautic.factory',
                    'mautic.tracker.contact',
                ],
            ],
            'mautic.point.model.triggerevent' => [
                'class' => \Mautic\PointBundle\Model\TriggerEventModel::class,
            ],
            'mautic.point.model.trigger' => [
                'class'     => \Mautic\PointBundle\Model\TriggerModel::class,
                'arguments' => [
                    'mautic.helper.ip_lookup',
                    'mautic.lead.model.lead',
                    'mautic.point.model.triggerevent',
                    'mautic.factory',
                    'mautic.tracker.contact',
                ],
            ],
        ],
        'repositories' => [
            'mautic.point.repository.lead_point_log' => [
                'class'     => Doctrine\ORM\EntityRepository::class,
                'factory'   => ['@doctrine.orm.entity_manager', 'getRepository'],
                'arguments' => [
                    \Mautic\PointBundle\Entity\LeadPointLog::class,
                ],
            ],
            'mautic.point.repository.lead_trigger_log' => [
                'class'     => Doctrine\ORM\EntityRepository::class,
                'factory'   => ['@doctrine.orm.entity_manager', 'getRepository'],
                'arguments' => [
                    \Mautic\PointBundle\Entity\LeadTriggerLog::class,
                ],
            ],
        ],
    ],
];
