<?php
/**
 ***********************************************************************************************
 * Modul Preferences of the admidio module CategoryReport
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * add     : add a configuration
 * delete  : delete a configuration
 * copy    : copy a configuration
 *
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/../../system/login_valid.php');

try {
    // only authorized user are allowed to start this module
    if (!$gCurrentUser->isAdministrator()) {
        throw new AdmException('SYS_NO_RIGHTS');
    }

    // Initialize and check the parameters
    $getAdd = admFuncVariableIsValid($_GET, 'add', 'bool');
    $getDelete = admFuncVariableIsValid($_GET, 'delete', 'numeric', array('defaultValue' => 0));
    $getCopy = admFuncVariableIsValid($_GET, 'copy', 'numeric', array('defaultValue' => 0));

    $report = new CategoryReport();
    $config = $report->getConfigArray();
    $catReportConfigs = array();

    $headline = $gL10n->get('SYS_CATEGORY_REPORT') . ' - ' . $gL10n->get('SYS_CONFIGURATIONS');

    if ($getAdd) {
        $config[] = array('id' => '', 'name' => '', 'col_fields' => '', 'selection_role' => '', 'selection_cat' => '', 'number_col' => '', 'default_conf' => false);
        // ohne $report->saveConfigArray(); ansonsten würden 'name' und 'col_fields' ohne Daten gespeichert sein
    }

    if ($getDelete > 0) {
        $config[$getDelete - 1]['id'] = $config[$getDelete - 1]['id'] * (-1);                   // id negieren, als Kennzeichen für "Deleted"
        $config = $report->saveConfigArray($config);
    }

    if ($getCopy > 0) {
        $config[] = array('id' => '',
            'name' => $report->createName($config[$getCopy - 1]['name']),
            'col_fields' => $config[$getCopy - 1]['col_fields'],
            'selection_role' => $config[$getCopy - 1]['selection_role'],
            'selection_cat' => $config[$getCopy - 1]['selection_cat'],
            'number_col' => $config[$getCopy - 1]['number_col'],
            'default_conf' => false);
        $config = $report->saveConfigArray($config);
    }

    $gNavigation->addUrl(CURRENT_URL, $gL10n->get('SYS_CONFIGURATIONS'));

// create html page object
    $page = new HtmlPage('plg-category-report-preferences', $headline);

    $page->addJavascript('
    $(".form-preferences").submit(function(event) {
        var id = $(this).attr("id");
        var action = $(this).attr("action");
        var formAlert = $("#" + id + " .form-alert");
        formAlert.hide();

        // disable default form submit
        event.preventDefault();

        $.post({
            url: action,
            data: $(this).serialize(),
            success: function(data) {
                if (data === "success") {

                    formAlert.attr("class", "alert alert-success form-alert");
                    formAlert.html("<i class=\"bi bi-check-lg\"></i><strong>' . $gL10n->get('SYS_SAVE_DATA') . '</strong>");
                    formAlert.fadeIn("slow");
                    formAlert.animate({opacity: 1.0}, 2500);
                    formAlert.fadeOut("slow");
                } else {
                    formAlert.attr("class", "alert alert-danger form-alert");
                    formAlert.fadeIn();
                    formAlert.html("<i class=\"bi bi-exclamation-circle-fill\"></i>" + data);
                }
            }
        });
    });',
        true
    );

    $javascriptCode = 'var arr_user_fields = createProfileFieldsArray();';

// create an array with the necessary data
    foreach ($config as $key => $value) {
        $catReportConfigs[$key] = $value['name'];
        $javascriptCode .= '

        var arr_default_fields' . $key . ' = createColumnsArray' . $key . '();
        var fieldNumberIntern' . $key . '  = 0;

    	// Function adds a new row for assigning columns to the list
    	function addColumn' . $key . '()
    	{
        	var category = "";
        	var table = document.getElementById("mylist_fields_tbody' . $key . '");
        	var newTableRow = table.insertRow(fieldNumberIntern' . $key . ');
        	newTableRow.setAttribute("id", "row" + (fieldNumberIntern' . $key . '))
        	var newCellCount = newTableRow.insertCell(-1);
        	newCellCount.innerHTML = (fieldNumberIntern' . $key . ' + 1) + ".&nbsp;' . $gL10n->get('SYS_COLUMN') . ':";

        	// New column for selecting the profile field
        	var newCellField = newTableRow.insertCell(-1);
        	htmlCboFields = "<select class=\"form-control\"  size=\"1\" id=\"column" + fieldNumberIntern' . $key . ' + "\" class=\"ListProfileField\" name=\"column' . $key . '_" + fieldNumberIntern' . $key . ' + "\">" +
                "<option value=\"\"></option>";
        	for(var counter = 1; counter < arr_user_fields.length; counter++)
        	{
            	if(category != arr_user_fields[counter]["cat_name"])
            	{
                	if(category.length > 0)
                	{
                    	htmlCboFields += "</optgroup>";
                	}
                	htmlCboFields += "<optgroup label=\"" + arr_user_fields[counter]["cat_name"] + "\">";
                	category = arr_user_fields[counter]["cat_name"];
            	}

            	var selected = "";

            	// for saved lists, select the corresponding profile field and add the field name to the list array
            	if(arr_default_fields' . $key . '[fieldNumberIntern' . $key . '])
            	{
                	if(arr_user_fields[counter]["id"] == arr_default_fields' . $key . '[fieldNumberIntern' . $key . ']["id"])
                	{
                    	selected = " selected=\"selected\" ";
                   	 arr_default_fields' . $key . '[fieldNumberIntern' . $key . ']["data"] = arr_user_fields[counter]["data"];
                	}
            	}
             	htmlCboFields += "<option value=\"" + arr_user_fields[counter]["id"] + "\" " + selected + ">" + arr_user_fields[counter]["data"] + "</option>";
        	}
        	htmlCboFields += "</select>";
        	newCellField.innerHTML = htmlCboFields;

        	$(newTableRow).fadeIn("slow");
        	fieldNumberIntern' . $key . '++;
    	}

    	function createColumnsArray' . $key . '()
    	{
        	var default_fields = new Array(); ';
        $fields = explode(',', $value['col_fields']);
        for ($number = 0; $number < count($fields); $number++) {
            // this is only to check whether this release still exists
            // it could be that a profile field or role has been deleted since the last save
            $found = $report->isInHeaderSelection($fields[$number]);
            if ($found > 0) {
                $javascriptCode .= '
                	default_fields[' . $number . '] 		  = new Object();
                	default_fields[' . $number . ']["id"]   = "' . $report->headerSelection[$found]["id"] . '";
                	default_fields[' . $number . ']["data"] = "' . $report->headerSelection[$found]["data"] . '";
                	';
            }
        }
        $javascriptCode .= '
        	return default_fields;
    	}
    	';
    }

    $javascriptCode .= '
    function createProfileFieldsArray()
    {
        var user_fields = new Array(); ';

// create an array for all columns with the necessary data
    foreach ($report->headerSelection as $key => $value) {
        $javascriptCode .= '
                user_fields[' . $key . '] 			= new Object();
                user_fields[' . $key . ']["id"]   	= "' . $report->headerSelection[$key]['id'] . '";
                user_fields[' . $key . ']["cat_name"] = "' . $report->headerSelection[$key]['cat_name'] . '";
                user_fields[' . $key . ']["data"]   	= "' . $report->headerSelection[$key]['data'] . '";
                ';
    }
    $javascriptCode .= '
        return user_fields;
    }
';
    $page->addJavascript($javascriptCode);
    $javascriptCodeExecute = '';

    foreach ($config as $key => $value) {
        $javascriptCodeExecute .= '
    	for(var counter = 0; counter < ' . count(explode(',', $value['col_fields'])) . '; counter++) {
        	addColumn' . $key . '();
    	}
    	';
    }
    $page->addJavascript($javascriptCodeExecute, true);

    $formConfigurations = new HtmlForm(
        'configurations_preferences_form', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category-report/preferences_function.php', array('form' => 'configurations')),
        $page, array('class' => 'form-preferences')
    );

    $formConfigurations->addDescription($gL10n->get('SYS_CONFIGURATIONS_HEADER'));
    $formConfigurations->addLine();
    $currentNumberConf = 0;

    foreach ($config as $key => $value) {
        $formConfigurations->openGroupBox('configurations_group', ++$currentNumberConf . '. ' . $gL10n->get('SYS_CONFIGURATION'));
        $formConfigurations->addInput('name' . $key, $gL10n->get('SYS_DESIGNATION'), $value['name'],
            array('property' => HtmlForm::FIELD_REQUIRED));
        $html = '
	   <div class="table-responsive">
    		<table class="table table-condensed" id="mylist_fields_table">
        		<thead>
            		<tr>
                		<th style="width: 20%;">' . $gL10n->get('SYS_ABR_NO') . '</th>
                		<th style="width: 37%;">' . $gL10n->get('SYS_CONTENT') . '</th>
            		</tr>
        		</thead>
        		<tbody id="mylist_fields_tbody' . $key . '">
            		<tr id="table_row_button">
                		<td colspan="2">
                    		<a class="icon-text-link" href="javascript:addColumn' . $key . '()"><i class="bi bi-plus-circle-fill"></i> ' . $gL10n->get('SYS_ADD_COLUMN') . '</a>
                		</td>
            		</tr>
        		</tbody>
    		</table>
    	</div>';
        $formConfigurations->addCustomContent($gL10n->get('SYS_COLUMN_SELECTION'), $html, array('helpTextId' => 'SYS_COLUMN_SELECTION_DESC'));

        $sql = 'SELECT rol_id, rol_name, cat_name
              FROM ' . TBL_CATEGORIES . ' , ' . TBL_ROLES . '
             WHERE cat_id = rol_cat_id
               AND ( cat_org_id = ' . $gCurrentOrgId . '
                OR cat_org_id IS NULL )';
        $formConfigurations->addSelectBoxFromSql('selection_role' . $key, $gL10n->get('SYS_ROLE_SELECTION'), $gDb, $sql,
            array('defaultValue' => explode(',', (string)$value['selection_role']), 'multiselect' => true, 'helpTextId' => 'SYS_ROLE_SELECTION_CONF_DESC'));

        $sql = 'SELECT cat_id, cat_name
              FROM ' . TBL_CATEGORIES . ' , ' . TBL_ROLES . '
             WHERE cat_id = rol_cat_id
               AND ( cat_org_id = ' . $gCurrentOrgId . '
                OR cat_org_id IS NULL )';
        $formConfigurations->addSelectBoxFromSql('selection_cat' . $key, $gL10n->get('SYS_CAT_SELECTION'), $gDb, $sql,
            array('defaultValue' => explode(',', (string)$value['selection_cat']), 'multiselect' => true, 'helpTextId' => 'SYS_CAT_SELECTION_CONF_DESC'));
        $formConfigurations->addCheckbox('number_col' . $key, $gL10n->get('SYS_QUANTITY') . ' (' . $gL10n->get('SYS_COLUMN') . ')', $value['number_col'], array('helpTextId' => 'SYS_NUMBER_COL_DESC'));
        $formConfigurations->addInput('id' . $key, '', $value['id'], array('property' => HtmlForm::FIELD_HIDDEN));
        $formConfigurations->addInput('default_conf' . $key, '', $value['default_conf'], array('property' => HtmlForm::FIELD_HIDDEN));
        $html = '<a id="copy_config" class="icon-text-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category-report/preferences.php', array('copy' => $key + 1)) . '">
            <i class="bi bi-copy"></i> ' . $gL10n->get('SYS_COPY_CONFIGURATION') . '</a>';
        if (count($config) > 1 && $value['default_conf'] == false) {
            $html .= '&nbsp;&nbsp;&nbsp;&nbsp;<a id="delete_config" class="icon-text-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category-report/preferences.php', array('delete' => $key + 1)) . '">
            <i class="bi bi-trash"></i> ' . $gL10n->get('SYS_DELETE_CONFIGURATION') . '</a>';
        }
        if (!empty($value['name'])) {
            $formConfigurations->addCustomContent('', $html);
        }
        $formConfigurations->closeGroupBox();
    }

    $formConfigurations->addLine();
    $html = '<a id="add_config" class="icon-text-link" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_MODULES . '/category-report/preferences.php', array('add' => 1)) . '">
            <i class="bi bi-plus-circle-fill"></i> ' . $gL10n->get('SYS_ADD_ANOTHER_CONFIG') . '
        </a>';
    $htmlDesc = '<div class="alert alert-warning alert-small" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>' . $gL10n->get('ORG_NOT_SAVED_SETTINGS_LOST') . '
            </div>';
    $formConfigurations->addCustomContent('', $html, array('helpTextId' => $htmlDesc));
    $formConfigurations->addSubmitButton('btn_save_configurations', $gL10n->get('SYS_SAVE'), array('icon' => 'bi-check-lg'));

    $page->addHtml($formConfigurations->show());

    $page->show();
} catch (AdmException|Exception|\Smarty\Exception $e) {
    $gMessage->show($e->getMessage());
}
