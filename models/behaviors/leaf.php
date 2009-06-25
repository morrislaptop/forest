<?php
/* SVN FILE: $Id$ */
/**
 * Tree behavior class.
 *
 * Enables a model object to act as a node-based tree.
 *
 * PHP versions 4 and 5
 *
 * CakePHP :  Rapid Development Framework (http://www.cakephp.org)
 * Copyright 2006-2008, Cake Software Foundation, Inc.
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright     Copyright 2006-2008, Cake Software Foundation, Inc.
 * @link          http://www.cakefoundation.org/projects/info/cakephp CakePHP Project
 * @package       cake
 * @subpackage    cake.cake.libs.model.behaviors
 * @since         CakePHP v 1.2.0.4487
 * @version       $Revision$
 * @modifiedby    $LastChangedBy$
 * @lastmodified  $Date$
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */
/**
 * Leaf Behavior.
 *
 * Sets some extra fields when dealing with tree structures.
 * It is useful to put this behavior AFTER the Tree in the actsAs array
 *
 * @see http://en.wikipedia.org/wiki/Tree_traversal
 * @package       cake
 * @subpackage    cake.cake.libs.model.behaviors
 */
class LeafBehavior extends ModelBehavior
{
/**
 * Defaults
 *
 * @var array
 * @access protected
 */
	var $_defaults = array(
		'parent' => 'parent_id', 'depth' => 'depth', 'sequence' => 'sequence',
		'left' => 'lft', 'right' => 'rght', 'first' => 'first', 'last' => 'last'
	);

/**
 * Initiate Leaf behavior
 *
 * @param object $Model instance of model
 * @param array $config array of configuration settings.
 * @return void
 * @access public
 */
	function setup(&$model, $config = array()) {
		$settings = array_merge($this->_defaults, $config);
		$this->settings[$model->alias] = $settings;
	}

/**
 * afterSave function
 *
 * @access public
 * @return void
 */
	function afterSave(&$model) {
		extract($this->settings[$model->alias]);
		if ( array_key_exists($parent, $model->data[$model->alias]) ) {
			$this->reset($model, $model->data[$model->alias][$parent]);
		}
		return true;
	}

/**
 * Before delete method. Called before all deletes
 *
 * Will reset all the siblings
 *
 * @param AppModel $Model Model instance
 * @return boolean true to continue, false to abort the delete
 * @access public
 */
	function beforeDelete(&$model) {
		extract($this->settings[$model->alias]);
		$this->reset($model, $model->field($parent));
		return true;
	}

/**
 * reset function
 *
 * @param mixed $parentId
 * @access public
 * @return void
 */
	function reset(&$model, $parentId = null) {
		$this->resetDepths($model, $parentId);
		$this->resetSequences($model, $parentId);
	}
/**
 * resetDepths function
 *
 * @param mixed $parentId
 * @access public
 * @return void
 */
	function resetDepths(&$model, $parentId = null) {
		extract($this->settings[$model->alias]);
		if ($parentId) {
			$conditions[$model->alias . '.' . $left . ' >'] = $model->field($left, array($model->primaryKey => $parentId));
			$conditions[$model->alias . '.' . $right . ' <'] = $model->field($right, array($model->primaryKey => $parentId));
		} else {
			$conditions = array();
			$table = $model->table;
			$idKey = $model->primaryKey;
			$model->query("UPDATE $table SET depth = (
				SELECT wrapper.parents FROM (
					SELECT
						this.$idKey as row,
						COUNT(parent.$idKey) as parents
					FROM
						$table AS this
					LEFT JOIN $table AS parent ON (
						parent.$left < this.$left AND
						parent.$right > this.$right)
					GROUP BY
						this.$idKey
				) AS wrapper WHERE wrapper.row = $table.$idKey)");
			$db =& ConnectionManager::getDataSource($model->useDbConfig);
			if (!$db->error) {
				return true;
			}
		}
		$nodes = $model->find('list', compact('conditions'));
		foreach ($nodes as $nodeId => $node) {
			$model->id = $nodeId;
			$parent = $model->getPath($nodeId, array($model->primaryKey));
			$model->saveField($depth, count($parent) - 1);
		}
		return true;
	}
/**
 * resetSequences function
 *
 * @param mixed $parentId
 * @param string $prefix
 * @param bool $start
 * @access public
 * @return void
 */
	function resetSequences(&$model, $parentId = null, $prefix = '', $start = true) {
		extract($this->settings[$model->alias]);
		if ($prefix == '' && $start == true && $parentId) {
			$model->id = $parentId;
			$depthVal = $model->field($depth);
			$prefix = $model->field($sequence);
		}
		$model->recursive = -1;
		$nodes = $model->findAllByParent_id($parentId, null, $left . ' ASC');
		$prefix = $prefix ? $prefix . '.' : $prefix;
		$index = $parentId ? 1 : '';
		if ($nodes) {
			$isFirst = 1;
			$isLast = 0;
			foreach ($nodes as $node) {
				$node = $node[$model->alias];
				$model->create();
				$model->id = $node[$model->primaryKey];
				$data = array(
					$sequence => $prefix . $index,
					$first => $isFirst,
					$last => $isLast
				);
				$model->save($data);
				$this->resetSequences($model, $node[$model->primaryKey], $prefix . $index, false);
				$index++;
				$isFirst = 0;
			}
			// mark the last.
			$model->saveField($last, 1);
		}
		return true;
	}
}
?>