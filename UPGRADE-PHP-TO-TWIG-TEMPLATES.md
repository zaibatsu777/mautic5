# Migrating PHP templates to Twig

Tip: if you're using VS Code, install this plugin: `bajdzis.vscode-twig-pack`

## Basic migration

```PHP
<?php
$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'mauticWebhook');
$view['slots']->set('headerTitle', $view['translator']->trans('mautic.webhook.webhooks'));
?>

// ROUTING
<a href="<?php echo $view['router']->path('/emails', ['objectAction' => 'batchDelete']); ?">Hello world!</a>

// FORMS
<?php echo $view['form']->start($form); ?>
<?php echo $view['form']->row($form['email']); ?>
<?php echo $view['form']->end($form); ?>

// PAGE ACTIONS
$view['slots']->set('actions', $view->render('MauticCoreBundle:Helper:page_actions.html.php', [
    'item'            => $item,
    'templateButtons' => [
        'edit'   => $view['security']->hasEntityAccess($permissions['webhook:webhooks:editown'], $permissions['webhook:webhooks:editother'], $item->getCreatedBy()),
        'clone'  => $permissions['webhook:webhooks:create'],
        'delete' => $view['security']->hasEntityAccess($permissions['webhook:webhooks:deleteown'], $permissions['webhook:webhooks:deleteown'], $item->getCreatedBy()),
    ],
    'routeBase' => 'webhook',
]));

// TODO add more examples
```

Becomes

```Twig
{% extends 'MauticCoreBundle:Default:content.html.twig' %}

{% block headerTitle %}{% trans %}mautic.webhook.webhooks{% endtrans %}{% endblock %}
{% block mauticContent %}mauticWebhook{% endblock %}

{# ROUTING #}
<a href="{{ path('/emails', {objectAction: 'batchDelete'}) }}">Hello world!</a>

{# FORMS #}
{{ form_start(form) }}
{{ form_row(form.email) }}
{{ form_end(form) }}

{# PAGE ACTIONS #}
{% block actions %}
    {{- include(
        'MauticCoreBundle:Helper:page_actions.html.twig', {
            item: item,
            templateButtons: {
                'edit': securityHasEntityAccess(
                    permissions['webhook:webhooks:editown'],
                    permissions['webhook:webhooks:editother'],
                    item.getCreatedBy()
                ),
                'clone': permissions['webhook:webhooks:create'],
                'delete': securityHasEntityAccess(
                    permissions['webhook:webhooks:deleteown'],
                    permissions['webhook:webhooks:deleteother'],
                    item.getCreatedBy()
                )
            },
            routeBase: 'webhook'
    }) -}}
{% endblock %}

{# TODO add more examples #}
```

## Random notes

- `strict_variables` is enabled both in dev mode (`config_dev.php`) and in prod mode (`config_prod.php`) to help you prevent bugs in your code. See the [Twig documentation](https://twig.symfony.com/doc/3.x/api.html#environment_options) for more details.
- If you extend `MauticCoreBundle:Default:content.html.twig`, everything HAS to be in blocks. Trying to put any HTML elements outside a block will fail with the following error:

    > A template that extends another one cannot include content outside Twig blocks.
- You're probably used to writing `if !empty($variable) {}` in PHP. That checks if the variable is set and whether it is not empty. In Twig, you explicity have to write `if variable is defined and variable is not empty`. It's a lot more descriptive. In some cases, you can use Twig's [default filter](https://twig.symfony.com/doc/3.x/filters/default.html), like:

    ```Twig
    Before:

    {% set nameGetter = nameGetter is defined and nameGetter is not empty ? nameGetter : 'getName' %}

    After:

    {% set nameGetter = nameGetter|default('getName') %}

    ```
