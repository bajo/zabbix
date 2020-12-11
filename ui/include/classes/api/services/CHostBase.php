<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


abstract class CHostBase extends CApiService {

	public const ACCESS_RULES = [
		'get' => ['min_user_type' => USER_TYPE_ZABBIX_USER],
		'create' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'update' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN],
		'delete' => ['min_user_type' => USER_TYPE_ZABBIX_ADMIN]
	];

	protected $tableName = 'hosts';
	protected $tableAlias = 'h';

	/**
	 * Links the templates to the given hosts.
	 *
	 * @param array $templateIds
	 * @param array $targetIds		an array of host IDs to link the templates to
	 *
	 * @return array 	an array of added hosts_templates rows, with 'hostid' and 'templateid' set for each row
	 */
	protected function link(array $templateIds, array $targetIds) {
		if (empty($templateIds)) {
			return;
		}

		// permission check
		$templateIds = array_unique($templateIds);

		$count = API::Template()->get([
			'countOutput' => true,
			'templateids' => $templateIds
		]);

		if ($count != count($templateIds)) {
			self::exception(ZBX_API_ERROR_PERMISSIONS, _('No permissions to referred object or it does not exist!'));
		}

		// check if someone passed duplicate templates in the same query
		$templateIdDuplicates = zbx_arrayFindDuplicates($templateIds);
		if (!zbx_empty($templateIdDuplicates)) {
			$duplicatesFound = [];
			foreach ($templateIdDuplicates as $value => $count) {
				$duplicatesFound[] = _s('template ID "%1$s" is passed %2$s times', $value, $count);
			}
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s('Cannot pass duplicate template IDs for the linkage: %1$s.', implode(', ', $duplicatesFound))
			);
		}

		// get DB templates which exists in all targets
		$res = DBselect('SELECT * FROM hosts_templates WHERE '.dbConditionInt('hostid', $targetIds));
		$mas = [];
		while ($row = DBfetch($res)) {
			if (!isset($mas[$row['templateid']])) {
				$mas[$row['templateid']] = [];
			}
			$mas[$row['templateid']][$row['hostid']] = 1;
		}
		$targetIdCount = count($targetIds);
		$commonDBTemplateIds = [];
		foreach ($mas as $templateId => $targetList) {
			if (count($targetList) == $targetIdCount) {
				$commonDBTemplateIds[] = $templateId;
			}
		}

		// check if there are any template with triggers which depends on triggers in templates which will be not linked
		$commonTemplateIds = array_unique(array_merge($commonDBTemplateIds, $templateIds));
		foreach ($templateIds as $templateid) {
			$triggerids = [];
			$dbTriggers = get_triggers_by_hostid($templateid);
			while ($trigger = DBfetch($dbTriggers)) {
				$triggerids[$trigger['triggerid']] = $trigger['triggerid'];
			}

			$sql = 'SELECT DISTINCT h.host'.
				' FROM trigger_depends td,functions f,items i,hosts h'.
				' WHERE ('.
				dbConditionInt('td.triggerid_down', $triggerids).
				' AND f.triggerid=td.triggerid_up'.
				' )'.
				' AND i.itemid=f.itemid'.
				' AND h.hostid=i.hostid'.
				' AND '.dbConditionInt('h.hostid', $commonTemplateIds, true).
				' AND h.status='.HOST_STATUS_TEMPLATE;
			if ($dbDepHost = DBfetch(DBselect($sql))) {
				$tmpTpls = API::Template()->get([
					'templateids' => $templateid,
					'output'=> API_OUTPUT_EXTEND
				]);
				$tmpTpl = reset($tmpTpls);

				self::exception(ZBX_API_ERROR_PARAMETERS,
					_s('Trigger in template "%1$s" has dependency with trigger in template "%2$s".', $tmpTpl['host'], $dbDepHost['host']));
			}
		}

		$res = DBselect(
			'SELECT ht.hostid,ht.templateid'.
				' FROM hosts_templates ht'.
				' WHERE '.dbConditionInt('ht.hostid', $targetIds).
				' AND '.dbConditionInt('ht.templateid', $templateIds)
		);
		$linked = [];
		while ($row = DBfetch($res)) {
			if (!isset($linked[$row['hostid']])) {
				$linked[$row['hostid']] = [];
			}
			$linked[$row['hostid']][$row['templateid']] = 1;
		}

		// add template linkages, if problems rollback later
		$hostsLinkageInserts = [];
		foreach ($targetIds as $targetid) {
			foreach ($templateIds as $templateid) {
				if (isset($linked[$targetid]) && isset($linked[$targetid][$templateid])) {
					continue;
				}
				$hostsLinkageInserts[] = ['hostid' => $targetid, 'templateid' => $templateid];
			}
		}
		DB::insert('hosts_templates', $hostsLinkageInserts);

		// check if all trigger templates are linked to host.
		// we try to find template that is not linked to hosts ($targetids)
		// and exists trigger which reference that template and template from ($templateids)
		$sql = 'SELECT DISTINCT h.host'.
			' FROM functions f,items i,triggers t,hosts h'.
			' WHERE f.itemid=i.itemid'.
			' AND f.triggerid=t.triggerid'.
			' AND i.hostid=h.hostid'.
			' AND h.status='.HOST_STATUS_TEMPLATE.
			' AND NOT EXISTS (SELECT 1 FROM hosts_templates ht WHERE ht.templateid=i.hostid AND '.dbConditionInt('ht.hostid', $targetIds).')'.
			' AND EXISTS (SELECT 1 FROM functions ff,items ii WHERE ff.itemid=ii.itemid AND ff.triggerid=t.triggerid AND '.dbConditionInt('ii.hostid', $templateIds). ')';
		if ($dbNotLinkedTpl = DBfetch(DBSelect($sql, 1))) {
			self::exception(
				ZBX_API_ERROR_PARAMETERS,
				_s('Trigger has items from template "%1$s" that is not linked to host.', $dbNotLinkedTpl['host'])
			);
		}

		// check template linkage circularity
		$res = DBselect(
			'SELECT ht.hostid,ht.templateid'.
				' FROM hosts_templates ht,hosts h'.
				' WHERE ht.hostid=h.hostid '.
				' AND h.status IN('.HOST_STATUS_MONITORED.','.HOST_STATUS_NOT_MONITORED.','.HOST_STATUS_TEMPLATE.')'
		);

		// build linkage graph and prepare list for $rootList generation
		$graph = [];
		$hasParentList = [];
		$hasChildList = [];
		$all = [];
		while ($row = DBfetch($res)) {
			if (!isset($graph[$row['hostid']])) {
				$graph[$row['hostid']] = [];
			}
			$graph[$row['hostid']][] = $row['templateid'];
			$hasParentList[$row['templateid']] = $row['templateid'];
			$hasChildList[$row['hostid']] = $row['hostid'];
			$all[$row['templateid']] = $row['templateid'];
			$all[$row['hostid']] = $row['hostid'];
		}

		// get list of templates without parents
		$rootList = [];
		foreach ($hasChildList as $parentId) {
			if (!isset($hasParentList[$parentId])) {
				$rootList[] = $parentId;
			}
		}

		// search cycles and double linkages in rooted parts of graph
		$visited = [];
		foreach ($rootList as $root) {
			$path = [];

			// raise exception on cycle or double linkage
			$this->checkCircularAndDoubleLinkage($graph, $root, $path, $visited);
		}

		// there is still possible cycles without root
		if (count($visited) < count($all)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, _('Circular template linkage is not allowed.'));
		}

		return $hostsLinkageInserts;
	}

	protected function unlink($templateids, $targetids = null) {
		$cond = ['templateid' => $templateids];
		if (!is_null($targetids)) {
			$cond['hostid'] =  $targetids;
		}
		DB::delete('hosts_templates', $cond);

		if (!is_null($targetids)) {
			$hosts = API::Host()->get([
				'hostids' => $targetids,
				'output' => ['hostid', 'host'],
				'nopermissions' => true
			]);
		}
		else{
			$hosts = API::Host()->get([
				'templateids' => $templateids,
				'output' => ['hostid', 'host'],
				'nopermissions' => true
			]);
		}

		if (!empty($hosts)) {
			$templates = API::Template()->get([
				'templateids' => $templateids,
				'output' => ['hostid', 'host'],
				'nopermissions' => true
			]);

			$hosts = implode(', ', zbx_objectValues($hosts, 'host'));
			$templates = implode(', ', zbx_objectValues($templates, 'host'));

			info(_s('Templates "%1$s" unlinked from hosts "%2$s".', $templates, $hosts));
		}
	}

	/**
	 * Searches for cycles and double linkages in graph.
	 *
	 * @throw APIException rises exception if cycle or double linkage is found
	 *
	 * @param array $graph - array with keys as parent ids and values as arrays with child ids
	 * @param int $current - cursor for recursive DFS traversal, starting point for algorithm
	 * @param array $path - should be passed empty array for DFS
	 * @param array $visited - there will be stored visited graph node ids
	 *
	 * @return boolean
	 */
	protected function checkCircularAndDoubleLinkage($graph, $current, &$path, &$visited) {
		if (isset($path[$current])) {
			if ($path[$current] == 1) {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Circular template linkage is not allowed.'));
			}
			else {
				self::exception(ZBX_API_ERROR_PARAMETERS, _('Template cannot be linked to another template more than once even through other templates.'));
			}
		}
		$path[$current] = 1;
		$visited[$current] = 1;

		if (isset($graph[$current])) {
			foreach ($graph[$current] as $next) {
				$this->checkCircularAndDoubleLinkage($graph, $next, $path, $visited);
			}
		}

		$path[$current] = 2;

		return false;
	}

	/**
	 * Creates new tags.
	 *
	 * @param array  $create_tags
	 * @param int    $create_tags[<hostid>]
	 * @param string $create_tags[<hostid>][]['tag']
	 * @param string $create_tags[<hostid>][]['value']
	 */
	protected function createTags(array $create_tags): void {
		$create = [];

		foreach ($create_tags as $hostid => $tags) {
			foreach ($tags as $tag) {
				$create[] = ['hostid' => $hostid] + $tag;
			}
		}

		if ($create) {
			DB::insert('host_tag', $create);
		}
	}

	/**
	 * Updates tags by deleting existing tags if they are not among the input tags, and adding missing ones.
	 *
	 * @param array  $host_tags
	 * @param int    $host_tags[<hostid>]
	 * @param string $host_tags[<hostid>][]['tag']
	 * @param string $host_tags[<hostid>][]['value']
	 */
	protected function updateTags(array $host_tags): void {
		if (!$host_tags) {
			return;
		}

		$insert = [];
		$db_tags = DB::select('host_tag', [
			'output' => ['hosttagid', 'hostid', 'tag', 'value'],
			'filter' => ['hostid' => array_keys($host_tags)],
			'preservekeys' => true
		]);

		$db_host_tags = [];
		foreach ($db_tags as $db_tag) {
			$db_host_tags[$db_tag['hostid']][] = $db_tag;
		}

		foreach ($host_tags as $hostid => $tags) {
			foreach (zbx_toArray($tags) as $tag) {
				if (array_key_exists($hostid, $db_host_tags)) {
					$tag += ['value' => ''];

					foreach ($db_host_tags[$hostid] as $db_tag) {
						if ($tag['tag'] === $db_tag['tag'] && $tag['value'] === $db_tag['value']) {
							unset($db_tags[$db_tag['hosttagid']]);
							$tag = null;
							break;
						}
					}
				}

				if ($tag !== null) {
					$insert[] = ['hostid' => $hostid] + $tag;
				}
			}
		}

		if ($db_tags) {
			DB::delete('host_tag', ['hosttagid' => array_keys($db_tags)]);
		}

		if ($insert) {
			DB::insert('host_tag', $insert);
		}
	}

	/**
	 * Adds the related objects requested by "select*" options to the resulting object set.
	 *
	 * @param array $options
	 * @param array $result   An object hash with PKs as keys.
	 *
	 * @return array mixed
	 */
	protected function addRelatedObjects(array $options, array $result) {
		$result = parent::addRelatedObjects($options, $result);
		$hostids = array_keys($result);

		// Add value mapping.
		if ($options['selectValueMaps'] !== null) {
			$fields = is_array($options['selectValueMaps'])
				? array_flip($options['selectValueMaps'])
				: ['name' => '', 'mappings' => ''];
			$valuemaps = $this->getValueMaps($hostids, $options['selectValueMaps']);

			foreach ($valuemaps as $valuemap) {
				$result[$valuemap['hostid']]['valuemaps'][] = array_intersect_key($valuemap, $fields);
			}
		}

		return $result;
	}


	/**
	 * Get value mapping for host/template/hostprototype as associative array where key is valuemapid.
	 *
	 * @param array        $hostids  Array of host/template/hostprototype ids.
	 * @param array|string $output   Array of value mapping fields to be returned or, API_OUTPUT_EXTEND for all fields.
	 * @return array
	 */
	protected function getValueMaps(array $hostids, $output): array {
		$params = [
			'output' => ['valuemapid', 'hostid'],
			'filter' => ['hostid' => $hostids]
		];

		if ($this->outputIsRequested('name', $output)) {
			$params['output'][] = 'name';
		}

		$valuemaps = DBfetchArrayAssoc(DBselect(DB::makeSql('valuemap', $params)), 'valuemapid');

		if ($this->outputIsRequested('mappings', $output) && $valuemaps) {
			$params = [
				'output' => ['valuemapid', 'value', 'newvalue'],
				'filter' => ['valuemapid' => array_keys($valuemaps)]
			];
			$query = DBselect(DB::makeSql('valuemap_mapping', $params));

			while ($mapping = DBfetch($query)) {
				$valuemaps[$mapping['valuemapid']]['mappings'][] = [
					'key' => $mapping['value'],
					'value' => $mapping['newvalue']
				];
			}
		}

		return $valuemaps;
	}

	/**
	 * Replace host/template/hostprototype value mapping.
	 *
	 * @param string $hostid     Id of host/template/hostprototype.
	 * @param array  $valuemaps  Array of new value maps data.
	 */
	protected function updateValueMaps($hostid, $valuemaps) {
		$this->deleteValueMaps([$hostid]);

		if ($valuemaps) {
			$this->createValueMaps($hostid, $valuemaps);
		}
	}

	/**
	 * Create value maps for host/template/hostprototype.
	 *
	 * @param string $hostid                   Id of host/template/hostprototype.
	 * @param array  $valuemaps                Array of value maps to be created.
	 * @param string $valuemaps[]['name']      Name of value mapping.
	 * @param string $valuemaps[]['mappings']  Array with arrays of 'key' and 'value' pair for single mapping.
	 */
	protected function createValueMaps($hostid, array $valuemaps) {
		$insert_rows = [];

		foreach ($valuemaps as $valuemap) {
			$insert_rows[] = [
				'hostid' => $hostid,
				'name' => $valuemap['name']
			];
		}

		$valuemapids = DB::insert('valuemap', $insert_rows, true);
		$valuemaps = array_combine($valuemapids, $valuemaps);
		$insert_rows = [];

		foreach ($valuemaps as $valuemapid => $valuemap) {
			foreach ($valuemap['mappings'] as $mapping) {
				$insert_rows[] = [
					'valuemapid' => $valuemapid,
					'value' => $mapping['key'],
					'newvalue' => $mapping['value']
				];
			}
		}

		DB::insert('valuemap_mapping', $insert_rows, true);
	}

	/**
	 * Delete value maps of specific host/template/hostprototype.
	 *
	 * @param array $hostids  Array of host ids.
	 */
	protected function deleteValueMaps(array $hostids) {
		DB::delete('valuemap', ['hostid' => $hostids]);
	}

	/**
	 * Validate valueMaps property.
	 *
	 * @param array  $valuemaps  Array of value maps to validate.
	 *
	 * @throws APIException if the input is invalid.
	 */
	protected function validateValueMaps(array $valuemaps) {
		$api_input_rules = ['type' => API_OBJECTS, 'uniq' => [['name']], 'fields' => [
			'name'	=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('valuemap', 'name')],
			'mappings'		=> ['type' => API_OBJECTS, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'uniq' => [['key']], 'fields' => [
				'key'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('valuemap_mapping', 'value')],
				'value'		=> ['type' => API_STRING_UTF8, 'flags' => API_REQUIRED | API_NOT_EMPTY, 'length' => DB::getFieldLength('valuemap_mapping', 'newvalue')]
			]]
		]];

		if (!CApiInputValidator::validate($api_input_rules, $valuemaps, '/', $error)) {
			self::exception(ZBX_API_ERROR_PARAMETERS, $error);
		}
	}
}
