# a&m nav

_Navigations in Craft, made easy_

## Functionality

In the plugin's settings you can adjust the plugin's name for your customers and disable adding, editing and deleting menus for non-admins.

![Settings](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/settings.jpg "Settings")

The menus overview.
If you disabled the **Can add, edit and delete** setting in the plugin's settings, non-admins will only see the created menus and the link to start building their navigation.

![Menus](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/menus.jpg "Menus")

When you create or edit a menu, you can set the following settings:

| Setting | Explanation |
| --------- | ----------- |
| Max Levels | The maximum number of levels this menu can have. Leave blank if you don’t care. |
| Can move from level | Whether non-admins can move pages from a specific level. Leave blank if you don’t care. |
| Can delete from level | Whether non-admins can delete pages from a specific level. Leave blank if you don’t care. |

![Edit](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/edit.jpg "Edit")

Let's start building a navigation!
Non-admins don't see the **Display menu** section. This is for admins only so they know what to put in the templates.
You have the option to add existing entries or..

![Build](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/build.jpg "Build")

.. add your own URLs.

![Your own URL](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/own-url.jpg "Your own URL")

When you have saved your pages in the menu, you can edit them later by double clicking on the page, or use the setting button behind the page when you hover over a page.

![Edit page](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/edit-page.jpg "Edit page")

## Variables

You have two ways to fetch your menu. You can get an array with your added pages, or let a&m nav create the HTML for you.

```
{% set nav = craft.amNav.getNavRaw("yourMenuHandle") %}

or

{{ craft.amNav.getNav("yourMenuHandle") }}
```

## Build the way you like it

Now you can add your own HTML if necessary!

```
{% set nav = craft.amNav.getNavRaw("yourMenuHandle") %}

{% macro addPageToNavigation(page) %}
    <li{% if page.active %} class="active"{% endif %}>
        <a href="{{ page.url }}" title="{{ page.name }}">{{ page.name }}</a>
        {% if page.children is defined %}
            <span class="navmain__more"></span>
            <div class="level{{ page.level }}">
                <span class="navmain__back">&lsaquo; Back</span>
                <ul>
                    {% for subpage in page.children %}
                        {{ _self.addPageToNavigation(subpage) }}
                    {% endfor %}
                </ul>
            </div>
        {% endif %}
    </li>
{% endmacro %}

<nav class="navmain">
    <ul class="level0">
        {% for page in nav %}
            {{ _self.addPageToNavigation(page) }}
        {% endfor %}
    </ul>
</nav>
```

### Parameters

| Parameter | Explanation |
| --------- | ----------- |
| maxLevel | Build the navigation till a certain level. |
| overrideStatus | Includes every page whatever the status. |
| startFromId | Begin the navigation at a specific page ID. |

## Let amnav do the trick

```
{{ craft.amNav.getNav("yourMenuHandle") }}
```

or with parameters..

```
{{ craft.amNav.getNav("yourMenuHandle", {
    id: 'navigation',
    class: 'navigation'
}) }}
```

### Parameters

| Parameter | Explanation |
| --------- | ----------- |
| id | ID for the navigation UL. |
| class | Class name for the navigation UL. |
| classActive | Class name for the active pages. |
| classBlank | Class name for hyperlinks that have a _blank target. |
| classLevel2 | Class name for the children UL. You can add a classLevel for every level you need (e.g.: classLevel2, classLevel3). |
| classChildren | Class name for a page that has children. |
| classFirst | Class name for the first page in the navigation. |
| excludeUl | Exclude the main UL wrapper. |
| maxLevel | Build the navigation till a certain level. |
| overrideStatus | Includes every page whatever the status. |
| startFromId | Begin the navigation at a specific page ID. |

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
| classLast | Class name for the last breadcrumb. |
| wrapper | Wrapper element without the < and >. |
| renameHome | Change the title of the home entry. |
| lastIsLink | Whether the last breadcrumb should be a link. |

## Contact

If you have any questions, suggestions or noticed any bugs, don't hesitate to contact us.