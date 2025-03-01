<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */

require_once dirname(__FILE__).'/js/configuration.host.edit.js.php';
require_once dirname(__FILE__).'/js/common.template.edit.js.php';

$widget = (new CWidget())
	->setTitle(_('Hosts'))
	->setNavigation(getHostNavigation('', $data['hostid']));

$divTabs = new CTabView();

if (!hasRequest('form_refresh')) {
	$divTabs->setSelected(0);
}

$frmHost = (new CForm())
	->setId('hosts-form')
	->setName('hostsForm')
	->setAttribute('aria-labeledby', ZBX_STYLE_PAGE_TITLE)
	->disablePasswordAutofill()
	->addVar('form', $data['form'])
	->addVar('clear_templates', $data['clear_templates'])
	->addVar('flags', $data['flags'])
	->addItem((new CVar('tls_connect', $data['tls_connect']))->removeId())
	->addVar('tls_accept', $data['tls_accept'])
	->addVar('psk_edit_mode', $data['psk_edit_mode']);

if ($data['hostid'] != 0) {
	$frmHost->addVar('hostid', $data['hostid']);
}
if ($data['clone_hostid'] != 0) {
	$frmHost->addVar('clone_hostid', $data['clone_hostid']);
}

$hostList = new CFormList('hostlist');

// LLD rule link
if ($data['readonly']) {
	$hostList->addRow(_('Discovered by'), $data['discoveryRule']
		? new CLink($data['discoveryRule']['name'],
			(new CUrl('host_prototypes.php'))
				->setArgument('form', 'update')
				->setArgument('parent_discoveryid', $data['discoveryRule']['itemid'])
				->setArgument('hostid', $data['hostDiscovery']['parent_hostid'])
				->setArgument('context', 'host')
		)
		: (new CSpan(_('Inaccessible discovery rule')))->addClass(ZBX_STYLE_GREY)
	);
}

$hostList
	->addRow(
		(new CLabel(_('Host name'), 'host'))->setAsteriskMark(),
		(new CTextBox('host', $data['host'], $data['readonly'], 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(_('Visible name'),
		(new CTextBox('visiblename', $data['visiblename'], $data['readonly'], 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
	)
	->addRow((new CLabel(_('Groups'), 'groups__ms'))->setAsteriskMark(),
		(new CMultiSelect([
			'name' => 'groups[]',
			'object_name' => 'hostGroup',
			'disabled' => $data['readonly'],
			'add_new' => (CWebUser::$data['type'] == USER_TYPE_SUPER_ADMIN),
			'data' => $data['groups_ms'],
			'popup' => [
				'parameters' => [
					'srctbl' => 'host_groups',
					'srcfld1' => 'groupid',
					'dstfrm' => $frmHost->getName(),
					'dstfld1' => 'groups_',
					'editable' => true
				]
			]
		]))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAriaRequired()
	);

// Interfaces for normal hosts.
if ($data['flags'] != ZBX_FLAG_DISCOVERY_CREATED) {
	zbx_add_post_js('window.hostInterfaceManager = new HostInterfaceManager('.json_encode($data['interfaces']).');');
	zbx_add_post_js('hostInterfaceManager.render();');

	$interface_header = renderInterfaceHeaders();

	$agent_interfaces = (new CDiv())
		->setId('agentInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
		->setAttribute('data-type', 'agent');

	$snmp_interfaces = (new CDiv())
		->setId('SNMPInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER.' '.ZBX_STYLE_LIST_VERTICAL_ACCORDION)
		->setAttribute('data-type', 'snmp');

	$jmx_interfaces = (new CDiv())
		->setId('JMXInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
		->setAttribute('data-type', 'jmx');

	$ipmi_interfaces = (new CDiv())
		->setId('IPMIInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
		->setAttribute('data-type', 'ipmi');

	$hostList->addRow(new CLabel(_('Interfaces')),
		[
			new CDiv([$interface_header, $agent_interfaces, $snmp_interfaces, $jmx_interfaces, $ipmi_interfaces]),
			new CDiv(
				(new CButton('', _('Add')))
					->addClass(ZBX_STYLE_BTN_LINK)
					->setMenuPopup([
						'type' => 'submenu',
						'data' => [
							'submenu' => getAddNewInterfaceSubmenu()
						]
					])
					->setAttribute('aria-label', _('Add new interface'))
			)
		]
	);
}
// Interfaces for discovered hosts.
else {
	zbx_add_post_js('window.hostInterfaceManager = new HostInterfaceManager('.json_encode($data['interfaces']).');');
	zbx_add_post_js('hostInterfaceManager.render();');
	zbx_add_post_js('HostInterfaceManager.makeReadonly();');

	$interface_header = renderInterfaceHeaders();

	$agent_interfaces = (new CDiv())
		->setId('agentInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
		->setAttribute('data-type', 'agent');

	$snmp_interfaces = (new CDiv())
		->setId('SNMPInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER.' '.ZBX_STYLE_LIST_VERTICAL_ACCORDION)
		->setAttribute('data-type', 'snmp');

	$jmx_interfaces = (new CDiv())
		->setId('JMXInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
		->setAttribute('data-type', 'jmx');

	$ipmi_interfaces = (new CDiv())
		->setId('IPMIInterfaces')
		->addClass(ZBX_STYLE_HOST_INTERFACE_CONTAINER)
		->setAttribute('data-type', 'ipmi');

	$hostList->addRow(new CLabel(_('Interfaces')),
		[new CDiv([$interface_header, $agent_interfaces, $snmp_interfaces, $jmx_interfaces, $ipmi_interfaces])]
	);
}

$hostList->addRow(_('Description'),
	(new CTextArea('description', $data['description']))
		->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		->setMaxlength(DB::getFieldLength('hosts', 'description'))
);

// Proxy
$hostList->addRow(new CLabel(_('Monitored by proxy'), 'label-proxy'),
	(new CSelect('proxy_hostid'))
		->setValue($data['proxy_hostid'])
		->setFocusableElementId('label-proxy')
		->setReadonly($data['readonly'])
		->addOptions(CSelect::createOptionsFromArray([0 => _('(no proxy)')] + $data['proxies']))
);

$hostList->addRow(_('Enabled'),
	(new CCheckBox('status', HOST_STATUS_MONITORED))->setChecked($data['status'] == HOST_STATUS_MONITORED)
);

$divTabs->addTab('hostTab', _('Host'), $hostList);

// templates
$tmplList = new CFormList();

// Templates for discovered hosts.
if ($data['readonly']) {
	$linkedTemplateTable = (new CTable())
		->setId('linked-template')
		->setHeader([_('Name')])
		->addStyle('width: 100%;');

	foreach ($data['linked_templates'] as $template) {
		$tmplList->addVar('templates[]', $template['templateid']);

		if ($data['allowed_ui_conf_templates']
				&& array_key_exists($template['templateid'], $data['writable_templates'])) {
			$template_link = (new CLink($template['name'],
				(new CUrl('templates.php'))
					->setArgument('form','update')
					->setArgument('templateid', $template['templateid'])
			))->setTarget('_blank');
		}
		else {
			$template_link = new CSpan($template['name']);
		}

		$linkedTemplateTable->addRow($template_link, null, 'conditions_'.$template['templateid']);
	}

	$tmplList->addRow(_('Linked templates'),
		(new CDiv($linkedTemplateTable))
			->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
			->addStyle('min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
	);
}
// Templates for normal hosts.
else {
	$disableids = [];

	$linkedTemplateTable = (new CTable())
		->setId('linked-template')
		->setAttribute('style', 'width: 100%;')
		->setHeader([_('Name'), _('Action')]);

	foreach ($data['linked_templates'] as $template) {
		$tmplList->addItem((new CVar('templates['.$template['templateid'].']', $template['templateid']))->removeId());

		if ($data['allowed_ui_conf_templates']
				&& array_key_exists($template['templateid'], $data['writable_templates'])) {
			$template_link = (new CLink($template['name'],
				'templates.php?form=update&templateid='.$template['templateid']
			))->setTarget('_blank');
		}
		else {
			$template_link = new CSpan($template['name']);
		}

		$linkedTemplateTable->addRow([
			$template_link,
			(new CCol(
				new CHorList([
					(new CSimpleButton(_('Unlink')))
						->onClick('javascript: submitFormWithParam('.
							'"'.$frmHost->getName().'", "unlink['.$template['templateid'].']", "1"'.
						');')
						->addClass(ZBX_STYLE_BTN_LINK),
					array_key_exists($template['templateid'], $data['original_templates'])
						? (new CSimpleButton(_('Unlink and clear')))
							->onClick('javascript: submitFormWithParam('.
								'"'.$frmHost->getName().'", "unlink_and_clear['.$template['templateid'].']", "1"'.
							');')
							->addClass(ZBX_STYLE_BTN_LINK)
						: null
				])
			))->addClass(ZBX_STYLE_NOWRAP)
		], null, 'conditions_'.$template['templateid']);

		$disableids[] = $template['templateid'];
	}

	$add_templates_ms = (new CMultiSelect([
		'name' => 'add_templates[]',
		'object_name' => 'templates',
		'data' => $data['add_templates'],
		'popup' => [
			'parameters' => [
				'srctbl' => 'templates',
				'srcfld1' => 'hostid',
				'srcfld2' => 'host',
				'dstfrm' => $frmHost->getName(),
				'dstfld1' => 'add_templates_',
				'disableids' => $disableids
			]
		]
	]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH);

	$tmplList
		->addRow(_('Linked templates'),
			(new CDiv($linkedTemplateTable))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		)
		->addRow((new CLabel(_('Link new templates'), 'add_templates__ms')),
			(new CDiv(
				(new CTable())->addRow([$add_templates_ms])
			))
				->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
				->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;')
		);
}

$divTabs->addTab('templateTab', _('Templates'), $tmplList, TAB_INDICATOR_LINKED_TEMPLATE);

/*
 * IPMI
 */
if ($data['readonly']) {
	$ipmi_authtype_select = [
		(new CTextBox('ipmi_authtype_name', ipmiAuthTypes($data['ipmi_authtype']), true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
		new CVar('ipmi_authtype', $data['ipmi_authtype'])
	];
	$ipmi_privilege_select = [
		(new CTextBox('ipmi_privilege_name', ipmiPrivileges($data['ipmi_privilege']), true))
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH),
		new CVar('ipmi_privilege', $data['ipmi_privilege'])
	];
}
else {
	$ipmi_authtype_select = new CListBox('ipmi_authtype', $data['ipmi_authtype'], 7, ipmiAuthTypes());
	$ipmi_privilege_select = new CListBox('ipmi_privilege', $data['ipmi_privilege'], 5, ipmiPrivileges());
}

$divTabs->addTab('ipmiTab', _('IPMI'),
	(new CFormList())
		->addRow(_('Authentication algorithm'), $ipmi_authtype_select)
		->addRow(_('Privilege level'), $ipmi_privilege_select)
		->addRow(_('Username'),
			(new CTextBox('ipmi_username', $data['ipmi_username'], $data['readonly']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->disableAutocomplete()
		)
		->addRow(_('Password'),
			(new CTextBox('ipmi_password', $data['ipmi_password'], $data['readonly']))
				->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
				->disableAutocomplete()
		)
);

// tags
$divTabs->addTab('tags-tab', _('Tags'), new CPartial('configuration.tags.tab', [
		'source' => 'host',
		'tags' => $data['tags'],
		'readonly' => $data['readonly']
	]), TAB_INDICATOR_TAGS
);

// macros
$tmpl = $data['show_inherited_macros'] ? 'hostmacros.inherited.list.html' : 'hostmacros.list.html';
$divTabs->addTab('macroTab', _('Macros'),
	(new CFormList('macrosFormList'))
		->addRow(null, (new CRadioButtonList('show_inherited_macros', (int) $data['show_inherited_macros']))
			->addValue(_('Host macros'), 0)
			->addValue(_('Inherited and host macros'), 1)
			->setModern(true)
		)
		->addRow(null, new CPartial($tmpl, [
			'macros' => $data['macros'],
			'readonly' => $data['readonly']
		]), 'macros_container'),
	TAB_INDICATOR_MACROS
);

$inventoryFormList = new CFormList('inventorylist');

$inventoryFormList->addRow(null,
	(new CRadioButtonList('inventory_mode', (int) $data['inventory_mode']))
		->addValue(_('Disabled'), HOST_INVENTORY_DISABLED)
		->addValue(_('Manual'), HOST_INVENTORY_MANUAL)
		->addValue(_('Automatic'), HOST_INVENTORY_AUTOMATIC)
		->setEnabled(!$data['readonly'])
		->setModern(true)
);
if ($data['readonly']) {
	$inventoryFormList->addVar('inventory_mode', $data['inventory_mode']);
}

$hostInventoryTable = DB::getSchema('host_inventory');
$hostInventoryFields = getHostInventories();

foreach ($hostInventoryFields as $inventoryNo => $inventoryInfo) {
	$field_name = $inventoryInfo['db_field'];

	if (!array_key_exists($field_name, $data['host_inventory'])) {
		$data['host_inventory'][$field_name] = '';
	}

	if ($hostInventoryTable['fields'][$field_name]['type'] == DB::FIELD_TYPE_TEXT) {
		$input = (new CTextArea('host_inventory['.$field_name.']', $data['host_inventory'][$field_name]))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH);
	}
	else {
		$input = (new CTextBox('host_inventory['.$field_name.']', $data['host_inventory'][$field_name]))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
			->setAttribute('maxlength', $hostInventoryTable['fields'][$field_name]['length']);
	}

	if ($data['inventory_mode'] == HOST_INVENTORY_DISABLED) {
		$input->setAttribute('disabled', 'disabled');
	}

	// link to populating item at the right side (if any)
	if (array_key_exists($inventoryNo, $data['inventory_items'])) {
		$name = $data['inventory_items'][$inventoryNo]['name_expanded'];

		$link = (new CLink($name,
			(new CUrl('items.php'))
				->setArgument('form', 'update')
				->setArgument('itemid', $data['inventory_items'][$inventoryNo]['itemid'])
				->setArgument('context', 'host')
				->getUrl()
		))->setTitle(_s('This field is automatically populated by item "%1$s".', $name));

		$inventory_item = (new CSpan([' &larr; ', $link]))->addClass('populating_item');
		if ($data['inventory_mode'] != HOST_INVENTORY_AUTOMATIC) {
			// those links are visible only in automatic mode
			$inventory_item->addStyle('display: none');
		}

		// this will be used for disabling fields via jquery
		$input->addClass('linked_to_item');
		if ($data['inventory_mode'] == HOST_INVENTORY_AUTOMATIC) {
			$input->setAttribute('disabled', 'disabled');
		}
	}
	else {
		$inventory_item = null;
	}

	$inventoryFormList->addRow($inventoryInfo['title'], [$input, $inventory_item]);
}

$divTabs->addTab('inventoryTab', _('Inventory'), $inventoryFormList, TAB_INDICATOR_INVENTORY);

// Encryption form list.
$encryption_form_list = (new CFormList('encryption'))
	->addRow(_('Connections to host'),
		(new CRadioButtonList('tls_connect', (int) $data['tls_connect']))
			->addValue(_('No encryption'), HOST_ENCRYPTION_NONE)
			->addValue(_('PSK'), HOST_ENCRYPTION_PSK)
			->addValue(_('Certificate'), HOST_ENCRYPTION_CERTIFICATE)
			->setModern(true)
			->setEnabled(!$data['readonly'])
	)
	->addRow(_('Connections from host'),
		(new CList())
			->addClass(ZBX_STYLE_LIST_CHECK_RADIO)
			->addItem((new CCheckBox('tls_in_none'))
				->setLabel(_('No encryption'))
				->setEnabled(!$data['readonly'])
			)
			->addItem((new CCheckBox('tls_in_psk'))
				->setLabel(_('PSK'))
				->setEnabled(!$data['readonly'])
			)
			->addItem((new CCheckBox('tls_in_cert'))
				->setLabel(_('Certificate'))
				->setEnabled(!$data['readonly'])
			)
	);

if ($data['psk_edit_mode']) {
	$encryption_form_list
		->addRow(
			(new CLabel(_('PSK identity'), 'tls_psk_identity'))->setAsteriskMark(),
			(new CTextBox('tls_psk_identity', $data['tls_psk_identity'], false, 128))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAriaRequired()
		)
		->addRow(
			(new CLabel(_('PSK'), 'tls_psk'))->setAsteriskMark(),
			(new CTextBox('tls_psk', $data['tls_psk'], false, 512))
				->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
				->setAriaRequired()
				->disableAutocomplete()
		);
}
else {
	$encryption_form_list
		->addRow(
			(new CLabel(_('PSK')))->setAsteriskMark(),
			(new CSimpleButton(_('Change PSK')))
				->onClick('javascript: submitFormWithParam("'.$frmHost->getName().'", "psk_edit_mode", "1");')
				->addClass(ZBX_STYLE_BTN_GREY)
				->setEnabled(!$data['readonly']),
			null,
			'tls_psk'
		);
}

$encryption_form_list
	->addRow(_('Issuer'),
		(new CTextBox('tls_issuer', $data['tls_issuer'], $data['readonly'], 1024))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
	)
	->addRow(_x('Subject', 'encryption certificate'),
		(new CTextBox('tls_subject', $data['tls_subject'], $data['readonly'], 1024))
			->setWidth(ZBX_TEXTAREA_BIG_WIDTH)
	);

$divTabs->addTab('encryptionTab', _('Encryption'), $encryption_form_list, TAB_INDICATOR_ENCRYPTION);

if ($data['flags'] != ZBX_FLAG_DISCOVERY_CREATED) {
	// Value mapping.
	$divTabs->addTab('valuemap-tab', _('Value mapping'), (new CFormList('valuemap-formlist'))->addRow(null,
		new CPartial('configuration.valuemap', [
			'source' => 'host',
			'valuemaps' => $data['valuemaps'],
			'readonly' => $data['readonly'],
			'form' => 'host'
		])),
		TAB_INDICATOR_VALUEMAPS
	);
}

/*
 * footer
 */
// Do not display the clone and delete buttons for clone forms and new host forms.
if ($data['hostid'] != 0) {
	$divTabs->setFooter(makeFormFooter(
		new CSubmit('update', _('Update')),
		[
			new CSubmit('clone', _('Clone')),
			new CSubmit('full_clone', _('Full clone')),
			new CButtonDelete(_('Delete selected host?'), url_param('form').url_param('hostid')),
			new CButtonCancel()
		]
	));
}
else {
	$divTabs->setFooter(makeFormFooter(
		new CSubmit('add', _('Add')),
		[new CButtonCancel()]
	));
}

$frmHost->addItem($divTabs);
$widget->addItem($frmHost);

$widget->show();
