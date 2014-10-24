# a&m nav

_Navigations in Craft, made easy_

## Functionality

In the plugin's settings you can adjust the plugin's name for your customers and disable adding, editing and deleting menus for non-admins.

![Settings](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/settings.jpg "Settings")

The menus overview.

![Menus](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/menus.jpg "Menus")

When you create or edit a menu, you can set the following settings:

![Edit](https://raw.githubusercontent.com/am-impact/am-impact.github.io/master/img/readme/amnav/edit.jpg "Edit")

Let's start building a navigation! You have the option to add existing entries or..

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

## Do it yourself way

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

## Let a&m nav do the trick

```
{{ craft.amNav.getNav("yourMenuHandle") }}
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
| excludeUl | Exclude the main UL wrapper. |
| maxLevel | Build the navigation till a certain level. |
| overrideStatus | Includes every page whatever the status. |
| startFromId | Begin the navigation at a specific page ID. |

## Contact

If you have any questions, suggestions or noticed any bugs, don't hesitate to contact us.