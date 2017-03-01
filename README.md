# a&m nav

_Navigations in Craft, made easy_

## Functionality

In the plugin's settings you can adjust the plugin's name for your customers and disable adding, editing and deleting navigations for non-admins.

![Settings](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/settings.jpg "Settings")

The navigations overview.
If you disabled the **Can add, edit and delete** setting in the plugin's settings, non-admins will only see the created navigations and the link to start building their navigation.

![Navigations](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/menus.jpg "Navigations")

When you create or edit a navigation, you can set the following settings:

| Setting | Explanation |
| --------- | ----------- |
| Max Levels | The maximum number of levels this navigation can have. Leave blank if you don’t care. |
| Can move from level | Whether non-admins can move nodes from a specific level. Leave blank if you don’t care. |
| Can delete from level | Whether non-admins can delete nodes from a specific level. Leave blank if you don’t care. |

![Edit](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/edit.jpg "Edit")

Let's start building a navigation!
Non-admins don't see the **Display navigation** section. This is for admins only so they know what to put in the templates.
You have the option to add existing entries, categories, assets or..

![Build](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/build.jpg "Build")

.. add your own URLs.

![Your own URL](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/own-url.jpg "Your own URL")

When you have saved your nodes in the navigation, you can edit them later by double clicking on the node, or use the setting button behind the node when you hover over a node.

![Edit node](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/edit-page.jpg "Edit node")

## Variables

You have two ways to fetch your navigation. You can get an array with your added nodes, or let a&m nav create the HTML for you.

```
{% set nav = craft.amNav.getNavRaw("yourNavigationHandle") %}

or

{{ craft.amNav.getNav("yourNavigationHandle") }}
```

## Build the way you like it

Now you can add your own HTML if necessary!

```
{% set nav = craft.amNav.getNavRaw("yourNavigationHandle") %}

{% macro addNodeToNavigation(node, index) %}
    {%- set nodeClasses = [] -%}
    {%- if node.hasChildren -%}
        {%- set nodeClasses = nodeClasses|merge(['has-children']) -%}
    {%- endif -%}
    {%- if node.active or node.hasActiveChild -%}
        {%- set nodeClasses = nodeClasses|merge(['active']) -%}
    {%- endif -%}
    {%- if node.level == 1 and index == 1 -%}
        {%- set nodeClasses = nodeClasses|merge(['first']) -%}
    {%- endif -%}
    {%- if node.listClass|length -%}
        {%- set nodeClasses = nodeClasses|merge([node.listClass]) -%}
    {%- endif -%}

    <li{% if nodeClasses|length %} class="{{ nodeClasses|join(' ') }}"{% endif %}>
        <a href="{{ node.url }}" title="{{ node.name }}"{% if node.blank %} target="_blank"{% endif %}>{{ node.name }}</a>
        {% if node.hasChildren %}
            <ul class="nav__level{{ (node.level + 1) }}">
                {% for subnode in node.children %}
                    {{ _self.addNodeToNavigation(subnode, loop.index) }}
                {% endfor %}
            </ul>
        {% endif %}
    </li>
{% endmacro %}

<nav class="navmain">
    <ul class="nav">
        {% for node in nav %}
            {{ _self.addNodeToNavigation(node, loop.index) }}
        {% endfor %}
    </ul>
</nav>
```

### Parameters

| Parameter | Explanation |
| --------- | ----------- |
| maxLevel | Build the navigation till a certain level. |
| overrideStatus | Includes every node whatever the status. |
| startFromId | Begin the navigation at a specific node ID. |

## Let amnav do the trick

```
{{ craft.amNav.getNav("yourNavigationHandle") }}
```

or with parameters..

```
{{ craft.amNav.getNav("yourNavigationHandle", {
    id: 'navigation',
    class: 'navigation'
}) }}
```

### Parameters

| Parameter | Explanation |
| --------- | ----------- |
| id | ID for the navigation UL. |
| class | Class name for the navigation UL. |
| classActive | Class name for the active nodes. |
| classBlank | Class name for hyperlinks that have a _blank target. |
| classLevel2 | Class name for the children UL. You can add a classLevel for every level you need (e.g.: classLevel2, classLevel3). |
| classChildren | Class name for a node that has children. |
| classFirst | Class name for the first node in the navigation. |
| excludeUl | Exclude the main UL wrapper. |
| maxLevel | Build the navigation till a certain level. |
| overrideStatus | Includes every node whatever the status. |
| startFromId | Begin the navigation at a specific node ID. |

## Breadcrumbs

Breadcrumbs are not based on a created navigation. They are based on the current URL segments.

```
{{ craft.amNav.getBreadcrumbs() }}
```

or with parameters..

```
{{ craft.amNav.getBreadcrumbs({
    id: 'breadcrumbs',
    class: 'breadcrumbs'
}) }}
```

### Parameters

| Parameter | Explanation |
| --------- | ----------- |
| id | ID for the breadcrumbs wrapper. |
| class | Class name for the breadcrumbs wrapper. |
| classDefault | Default class name for every breadcrumb. |
| classFirst | Class name for the first breadcrumb. |
| classLast | Class name for the last breadcrumb. |
| wrapper | Wrapper element without the < and >. |
| beforeText | Text before the first item, like 'You are here:'. |
| renameHome | Change the title of the home entry. |
| lastIsLink | Whether the last breadcrumb should be a link. |

## Contact

If you have any questions, suggestions or noticed any bugs, don't hesitate to contact us.
