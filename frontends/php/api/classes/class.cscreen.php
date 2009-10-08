<?php
/*
** ZABBIX
** Copyright (C) 2000-2009 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php
/**
 * File containing CScreen class for API.
 * @package API
 */
/**
 * Class containing methods for operations with Screens
 */
class CScreen extends CZBXAPI{
/**
 * Get Screen data
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $options
 * @param array $options['nodeids'] Node IDs
 * @param boolean $options['with_items'] only with items
 * @param boolean $options['editable'] only with read-write permission. Ignored for SuperAdmins
 * @param int $options['extendoutput'] return all fields for Hosts
 * @param int $options['count'] count Hosts, returned column name is rowscount
 * @param string $options['pattern'] search hosts by pattern in host names
 * @param int $options['limit'] limit selection
 * @param string $options['order'] depricated parametr (for now)
 * @return array|boolean Host data as array or false if error
 */
	public static function get($options=array()){
		global $USER_DETAILS;

		$result = array();
		$user_type = $USER_DETAILS['type'];
		$userid = $USER_DETAILS['userid'];

		$sort_columns = array('name'); // allowed columns for sorting


		$sql_parts = array(
			'select' => array('screens' => 's.screenid'),
			'from' => array('screens s'),
			'where' => array(),
			'order' => array(),
			'limit' => null);

		$def_options = array(
			'nodeids'					=> null,
			'screenids'					=> null,
			'editable'					=> null,
			'nopermissions'				=> null,
// OutPut
			'extendoutput'				=> null,
			'select_groups'				=> null,
			'select_templates'			=> null,
			'select_items'				=> null,
			'select_triggers'			=> null,
			'select_graphs'				=> null,
			'select_applications'		=> null,
			'count'						=> null,
			'pattern'					=> '',
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);

		$options = zbx_array_merge($def_options, $options);

// editable + PERMISSION CHECK
		if(defined('ZBX_API_REQUEST')){
			$options['nopermissions'] = false;
		}

// nodeids
		$nodeids = $options['nodeids'] ? $options['nodeids'] : get_current_nodeid(false);

// screenids
		if(!is_null($options['screenids'])){
			zbx_value2array($options['screenids']);
			$sql_parts['where'][] = DBcondition('s.screenid', $options['screenids']);
		}

// extendoutput
		if(!is_null($options['extendoutput'])){
			$sql_parts['select']['screens'] = 's.*';
		}

// count
		if(!is_null($options['count'])){
			$options['sortfield'] = '';

			$sql_parts['select'] = array('count(DISTINCT s.screenid) as rowscount');
		}

// pattern
		if(!zbx_empty($options['pattern'])){
			$sql_parts['where'][] = ' UPPER(s.name) LIKE '.zbx_dbstr('%'.strtoupper($options['pattern']).'%');
		}

// order
// restrict not allowed columns for sorting
		$options['sortfield'] = str_in_array($options['sortfield'], $sort_columns) ? $options['sortfield'] : '';
		if(!zbx_empty($options['sortfield'])){
			$sortorder = ($options['sortorder'] == ZBX_SORT_DOWN)?ZBX_SORT_DOWN:ZBX_SORT_UP;

			$sql_parts['order'][] = 's.'.$options['sortfield'].' '.$sortorder;

			if(!str_in_array('s.'.$options['sortfield'], $sql_parts['select']) && !str_in_array('s.*', $sql_parts['select'])){
				$sql_parts['select'][] = 's.'.$options['sortfield'];
			}
		}

// limit
		if(zbx_ctype_digit($options['limit']) && $options['limit']){
			$sql_parts['limit'] = $options['limit'];
		}
//-------

		$screenids = array();

		$sql_parts['select'] = array_unique($sql_parts['select']);
		$sql_parts['from'] = array_unique($sql_parts['from']);
		$sql_parts['where'] = array_unique($sql_parts['where']);
		$sql_parts['order'] = array_unique($sql_parts['order']);

		$sql_select = '';
		$sql_from = '';
		$sql_where = '';
		$sql_order = '';
		if(!empty($sql_parts['select']))	$sql_select.= implode(',',$sql_parts['select']);
		if(!empty($sql_parts['from']))		$sql_from.= implode(',',$sql_parts['from']);
		if(!empty($sql_parts['where']))		$sql_where.= ' AND '.implode(' AND ',$sql_parts['where']);
		if(!empty($sql_parts['order']))		$sql_order.= ' ORDER BY '.implode(',',$sql_parts['order']);
		$sql_limit = $sql_parts['limit'];

		$sql = 'SELECT '.$sql_select.'
				FROM '.$sql_from.'
				WHERE '.DBin_node('s.screenid', $nodeids).
				$sql_where.
				$sql_order;
		$res = DBselect($sql, $sql_limit);

		while($screen = DBfetch($res)){
			if($options['count'])
				$result = $screen;
			else{
				$screenids[$screen['screenid']] = $screen['screenid'];

				if(is_null($options['extendoutput'])){
					$result[$screen['screenid']] = $screen['screenid'];
				}
				else{
					if(!isset($result[$screen['screenid']])) $result[$screen['screenid']]= array();

					$result[$screen['screenid']] += $screen;
				}
			}
		}


		if((USER_TYPE_SUPER_ADMIN == $user_type) || $options['nopermissions']){}
		else{
			if(!empty($result)){
				$graphs_to_check = array();
				$items_to_check = array();
				$maps_to_check = array();
				$screens_to_check = array();
				$screens_items = array();

				$db_sitems = DBselect('SELECT * FROM screens_items WHERE '.DBcondition('screenid', $screenids));
				while($sitem = DBfetch($db_sitems)){
					$screens_items[$sitem['screenitemid']] = $sitem;

					if($sitem['resourceid'] == 0) continue;

					switch($sitem['resourcetype']){
						case SCREEN_RESOURCE_GRAPH:
							$graphs_to_check[] = $sitem['resourceid'];
						break;
						case SCREEN_RESOURCE_SIMPLE_GRAPH:
							$items_to_check[] = $sitem['resourceid'];
						break;
						case SCREEN_RESOURCE_MAP:
							$maps_to_check[] = $sitem['resourceid'];
						break;
						case SCREEN_RESOURCE_SCREEN:
							$screens_to_check[] = $sitem['resourceid'];
						break;
					}
				}
	// sdii($graphs_to_check);
	// sdii($items_to_check);
	// sdii($maps_to_check);
	// sdii($screens_to_check);

				$allowed_graphs = CGraph::get(array('graphids' => $graphs_to_check, 'editable' => isset($options['editable'])));
				$allowed_items = CItem::get(array('itemids' => $items_to_check, 'editable' => isset($options['editable'])));
				$allowed_maps = CMap::get(array('sysmapids' => $maps_to_check, 'editable' => isset($options['editable'])));
				$allowed_screens = CScreen::get(array('screenids' => $screens_to_check, 'editable' => isset($options['editable'])));

				$restr_graphs = array_diff($graphs_to_check, $allowed_graphs);
				$restr_items = array_diff($items_to_check, $allowed_items);
				$restr_maps = array_diff($maps_to_check, $allowed_maps);
				$restr_screens = array_diff($screens_to_check, $allowed_screens);


				foreach($restr_graphs as $resourceid){
					foreach($screens_items as $screen_itemid => $screen_item){
						if(($screen_item['resourceid'] == $resourceid) && ($screen_item['resourcetype'] == SCREEN_RESOURCE_GRAPH)){
							unset($result[$screen_item['screenid']]);
							unset($screens_items[$screen_itemid]);
						}
					}
				}
				foreach($restr_items as $resourceid){
					foreach($screens_items as $screen_itemid => $screen_item){
						if($screen_item['resourceid'] == $resourceid && ($screen_item['resourcetype'] == SCREEN_RESOURCE_SIMPLE_GRAPH)){
							unset($result[$screen_item['screenid']]);
							unset($screens_items[$screen_itemid]);
						}
					}
				}
				foreach($restr_maps as $resourceid){
					foreach($screens_items as $screen_itemid => $screen_item){
						if($screen_item['resourceid'] == $resourceid && ($screen_item['resourcetype'] == SCREEN_RESOURCE_MAP)){
							unset($result[$screen_item['screenid']]);
							unset($screens_items[$screen_itemid]);
						}
					}
				}
				foreach($restr_screens as $resourceid){
					foreach($screens_items as $screen_itemid => $screen_item){
						if($screen_item['resourceid'] == $resourceid && ($screen_item['resourcetype'] == SCREEN_RESOURCE_SCREEN)){
							unset($result[$screen_item['screenid']]);
							unset($screens_items[$screen_itemid]);
						}
					}
				}
			}
		}

		if(is_null($options['extendoutput']) || !is_null($options['count'])) return $result;


// Adding Items
		if($options['select_items']){
			if(!isset($screens_items)){
				$db_sitems = DBselect('SELECT * FROM screens_items WHERE '.DBcondition('screenid', $screenids));
				while($sitem = DBfetch($db_sitems)){
					$screens_items[$sitem['screenitemid']] = $sitem;
				}
			}
			foreach($screens_items as $sitem){
				if(!isset($result[$sitem['screenid']]['itemids'])){
					$result[$sitem['screenid']]['itemids'] = array();
					$result[$sitem['screenid']]['items'] = array();
				}
				$result[$sitem['screenid']]['itemids'][$sitem['screenitemid']] = $sitem['screenitemid'];
				$result[$sitem['screenid']]['items'][$sitem['screenitemid']] = $sitem;
			}
		}

	return $result;
	}

/**
 * Gets all Screen data from DB by Screen ID
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $screen_data
 * @param string $screen_data['screenid']
 * @return array|boolean Screen data as array or false if error
 */
	public static function getById($screen_data){
		$sql = 'SELECT * FROM screens WHERE screenid='.$screen_data['screenid'];
		$screen = DBfetch(DBselect($sql));

		$result = $screen ? true : false;
		if($result)
			return $screen;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_NO_HOST, 'data' => 'screen with id: '.$screen_data['screenid'].' doesn\'t exists.');
			return false;
		}
	}


/**
 * Add Screen
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $screens
 * @param string $screens['name']
 * @param array $screens['hsize']
 * @param int $screens['vsize']
 * @return boolean | array
 */
	public static function add($screens){

		$error = 'Unknown ZABBIX internal error';
		$result_ids = array();
		$result = false;

		self::BeginTransaction(__METHOD__);
		foreach($screens as $screen){

			$screen_db_fields = array(
				'name' => null,
				'hsize' => 3,
				'vsize' => 2
			);

			if(!check_db_fields($screen_db_fields, $screen)){
				$result = false;
				$error = 'Wrong fields for screen [ '.$screen['name'].' ]';
				break;
			}

			$screenid = get_dbid('screens', 'screenid');
			$sql = 'INSERT INTO screens (screenid, name, hsize, vsize) '.
				" VALUES ($screenid, ".zbx_dbstr($screen['name']). ", {$screen['hsize']}, {$screen['vsize']})";
			$result = DBexecute($sql);

			if(!$result) break;

			$result_ids[$screenid] = $screenid;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return $result_ids;
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => $error);//'Internal zabbix error');
			return false;
		}
	}

/**
 * Update Screen
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param _array $screens multidimensional array with Hosts data
 * @param string $screens['screenid']
 * @param int $screens['name']
 * @param int $screens['hsize']
 * @param int $screens['vsize']
 * @return boolean
 */
	public static function update($screens){

		$result = false;

		self::BeginTransaction(__METHOD__);
		foreach($screens as $screen){

			$screen_db_fields = CScreen::getById($screen['screenid']);

			if(!$screen_db_fields){
				$result = false;
				break;
			}

			if(!check_db_fields($screen_db_fields, $screen)){
				$result = false;
				break;
			}

			$sql = 'UPDATE screens SET name='.zbx_dbstr($name).", hsize={$screen['hsize']}, vsize={$screen['vsize']}
				WHERE screenid={$screen['screenid']}";
			$result = DBexecute($sql);

			if(!$result) break;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result){
			return true;
		}
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}


/**
 * Delete Screen
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $screenids
 * @return boolean
 */
	public static function delete($screenids){
		$result = true;

		self::BeginTransaction(__METHOD__);
		foreach($screenids as $screenid){
			$result = DBexecute('DELETE FROM screens_items WHERE screenid='.$screenid);
			$result &= DBexecute('DELETE FROM screens_items WHERE resourceid='.$screenid.' AND resourcetype='.SCREEN_RESOURCE_SCREEN);
			$result &= DBexecute('DELETE FROM slides WHERE screenid='.$screenid);
			$result &= DBexecute("DELETE FROM profiles WHERE idx='web.favorite.screenids' AND source='screenid' AND value_id=$screenid");
			$result &= DBexecute('DELETE FROM screens WHERE screenid='.$screenid);
			if(!$result) break;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result)
			return true;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

/**
 * add ScreenItem
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $screen_items
 * @param int $screen_items['screenid']
 * @param int $screen_items['resourcetype']
 * @param int $screen_items['x']
 * @param int $screen_items['y']
 * @param int $screen_items['resourceid']
 * @param int $screen_items['width']
 * @param int $screen_items['height']
 * @param int $screen_items['colspan']
 * @param int $screen_items['rowspan']
 * @param int $screen_items['elements']
 * @param int $screen_items['valign']
 * @param int $screen_items['halign']
 * @param int $screen_items['style']
 * @param int $screen_items['url']
 * @param int $screen_items['dynamic']
 * @return boolean
 */
	public static function setItems($screen_items){

		$result = true;

		self::BeginTransaction(__METHOD__);
		foreach($screen_items as $screen_item){

			extract($screen_item);
			$sql="DELETE FROM screens_items WHERE screenid=$screenid AND x=$x AND y=$y";
			DBexecute($sql);

			$screenitemid = get_dbid('screens_items', 'screenitemid');
			$sql = 'INSERT INTO screens_items '.
				'(screenitemid, resourcetype, screenid, x, y, resourceid, width, height, '.
				' colspan, rowspan, elements, valign, halign, style, url, dynamic) '.
				' VALUES '.
				"($screenitemid, $resourcetype, $screenid, $x, $y, $resourceid, $width, $height, $colspan, ".
				"$rowspan, $elements, $valign, $halign, $style, ".zbx_dbstr($url).", $dynamic)";
			$result = DBexecute($sql);

			if(!$result) break;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result)
			return true;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

/**
 * delete ScreenItem
 *
 * {@source}
 * @access public
 * @static
 * @since 1.8
 * @version 1
 *
 * @param array $screen_itemids
 * @return boolean
 */
	public static function deleteItems($screen_itemids){
		$result = true;

		self::BeginTransaction(__METHOD__);
		foreach($screen_items as $screen_itemid){
			$sql='DELETE FROM screens_items WHERE screenitemid='.$screen_itemid;
			if(!$result) break;
		}
		$result = self::EndTransaction($result, __METHOD__);

		if($result)
			return true;
		else{
			self::$error[] = array('error' => ZBX_API_ERROR_INTERNAL, 'data' => 'Internal zabbix error');
			return false;
		}
	}

}
?>
