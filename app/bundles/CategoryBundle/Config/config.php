<?php

return [
    'routes' => [
        'main' => [
            'mautic_category_batch_contact_set' => [
                'path'       => '/categories/batch/contact/set',
                'controller' => 'Mautic\CategoryBundle\Controller\BatchContactController::execAction',
            ],
            'mautic_category_batch_contact_view' => [
                'path'       => '/categories/batch/contact/view',
                'controller' => 'Mautic\CategoryBundle\Controller\BatchContactController::indexAction',
            ],
            'mautic_category_index' => [
                'path'       => '/categories/{bundle}/{page}',
                'controller' => 'Mautic\CategoryBundle\Controller\CategoryController::indexAction',
                'defaults'   => [
                    'bundle' => 'category',
                ],
            ],
            'mautic_category_action' => [
                'path'       => '/categories/{bundle}/{objectAction}/{objectId}',
                'controller' => 'Mautic\CategoryBundle\Controller\CategoryController::executeCategoryAction',
                'defaults'   => [
                    'bundle' => 'category',
                ],
            ],
        ],
        'api' => [
            'mautic_api_categoriesstandard' => [
                'standard_entity' => true,
                'name'            => 'categories',
                'path'            => '/categories',
                'controller'      => 'Mautic\CategoryBundle\Controller\Api\CategoryApiController',
            ],
        ],
    ],

    'menu' => [
        'admin' => [
            'mautic.category.menu.index' => [
                'route'     => 'mautic_category_index',
                'access'    => 'category:categories:view',
                'iconClass' => 'fa-folder',
                'id'        => 'mautic_category_index',
            ],
        ],
    ],

    'services' => [
        'events' => [
            'mautic.category.subscriber' => [
                'class'     => \Mautic\CategoryBundle\EventListener\CategorySubscriber::class,
                'arguments' => [
                    'mautic.helper.bundle',
                    'mautic.helper.ip_lookup',
                    'mautic.core.model.auditlog',
                ],
            ],
            'mautic.category.button.subscriber' => [
                'class'     => \Mautic\CategoryBundle\EventListener\ButtonSubscriber::class,
                'arguments' => [
                    'router',
                    'translator',
                ],
            ],
        ],
        'forms' => [
            'mautic.form.type.category' => [
                'class'     => 'Mautic\CategoryBundle\Form\Type\CategoryListType',
                'arguments' => [
                    'doctrine.orm.entity_manager',
                    'translator',
                    'mautic.category.model.category',
                    'router',
                ],
            ],
            'mautic.form.type.category_form' => [
                'class'     => \Mautic\CategoryBundle\Form\Type\CategoryType::class,
                'arguments' => [
                    'session',
                ],
            ],
            'mautic.form.type.category_bundles_form' => [
                'class'     => 'Mautic\CategoryBundle\Form\Type\CategoryBundlesType',
                'arguments' => [
                    'event_dispatcher',
                ],
            ],
        ],
        'models' => [
            'mautic.category.model.category' => [
                'class'     => 'Mautic\CategoryBundle\Model\CategoryModel',
                'arguments' => [
                    'request_stack',
                ],
            ],
            'mautic.category.model.contact.action' => [
                'class'     => \Mautic\CategoryBundle\Model\ContactActionModel::class,
                'arguments' => [
                    'mautic.lead.model.lead',
                ],
            ],
        ],
    ],
];
