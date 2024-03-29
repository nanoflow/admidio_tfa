
<div id="{$id}_group" class="mb-4 {if $property eq 1}admidio-form-group-required{/if}">
    <label for="{$id}" class="form-label">
        {include file='sys-template-parts/parts/form.part.icon.tpl'}
        {$label}
    </label>
    <select class="form-select" {foreach $data.attributes as $itemvar}
        {$itemvar@key}="{$itemvar}" {/foreach}>
        {assign "group" ""}
        {foreach $values as $optionvar}
            {if {array_key_exists key="group" array=$optionvar} && $optionvar["group"] neq $group}
                {if $group neq ""}</optgroup>{/if}
                <optgroup label="{$optionvar["group"]}">
                {assign "group" "{$optionvar["group"]}"}
            {/if}
            <option value="{$optionvar["id"]}" {if $defaultValue eq $optionvar["id"]}selected="selected"{/if}>{$optionvar["value"]}</option>
        {/foreach}
        {if $group neq ""}</optgroup>{/if}
    </select>

    {include file='sys-template-parts/parts/form.part.helptext.tpl'}
    {include file='sys-template-parts/parts/form.part.warning.tpl'}
</div>
