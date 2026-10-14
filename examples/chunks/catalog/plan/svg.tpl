{set $scheme = $_modx->runSnippet('PlanPointRender', [
    'floorId' => $_modx->resource.id,
    'inner' => 1
])}

{if $scheme | trim}
    <div class="plan-svg">
        {$scheme}

        <div class="plan-street">
            {set $id_house = $_modx->resource.parent | resource : 'parent'}
            {set $street = $_modx->resource.parent | resource : 'plan_street' ? : $id_house | resource : 'plan_street'}
            {foreach ($street | fromJSON) as $streetItem}
                <div class="plan-street-item --{$streetItem.position}"><span>{$streetItem.title}</span></div>
            {/foreach}
        </div>

        {$_modx->runSnippet('!msProducts', [
            'parents' => $_modx->resource.id,
            'limit' => '0',
            'where' => [
                'template' => '4'
            ],
            'tpl' => '@INLINE <div class="pin-item" data-i="x{$section}-{$layout_code_custom ?: $layout_code}"><span>{if $id_property_type == "1"}{set $title_flats}{if $rooms != "0"}{$rooms}{if $europlan ?}Е{/if}{else}Ст{/if}{/set}{else}{set $title_flats = $number}{/if}{$title_flats}</span></div>',
            'frontend_css' => ''
        ])}
    </div>
{else}
    <div class="plan-svg --emply">
        <div class="popup-form__title">Схема скоро появится</div>
        <p class="popup-form__desc">Планировка этого этажа пока не добавлена</p>
    </div>
{/if}
