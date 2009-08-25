<?php
class MapComponent extends Object
{
	var $model;
	var $controller;
	var $settings;

	//called before Controller::beforeFilter()
	function initialize(&$controller) {
		$this->controller =& $controller;
		$modelName = reset($controller->modelNames);
		$this->model = &$controller->$modelName;
	}

	//called after Controller::beforeFilter()
	function startup(&$controller)
	{
		$action = $controller->params['action'];

		// Methods we take over here - check the controller doesnt have their own method for it.
		if ( !method_exists($controller, $controller->params['action']) )
		{
			if ( in_array($action, array('nodes', 'admin_nodes')) ) {
				$this->nodes();
				return;
			}
			if ( in_array($action, array('reorder', 'admin_reorder')) ) {
				$this->reorder();
				return;
			}
			if ( in_array($action, array('reparent', 'admin_reparent')) ) {
				$this->reparent();
				return;
			}
		}
	}

	function nodes()
	{
	    // retrieve the node id that Ext JS posts via ajax
	    $parent = isset($this->controller->params['form']['node']) ? intval($this->controller->params['form']['node']) : null;

	    // find all the nodes underneath the parent node defined above
	    // the second parameter (true) means we only want direct children
	    $nodes = $this->model->children($parent, true);
	    $jsonNodes = array();
	    foreach ($nodes as $node) {
			$jsonNodes[] = array(
				'id' => $node[$this->model->alias][$this->model->primaryKey],
				'title' => $node[$this->model->alias][$this->model->displayField],
				'lft' => $node[$this->model->alias]['lft'],
				'rght' => $node[$this->model->alias]['rght'],
			);
	    }
	    $nodes = $jsonNodes;

	    // send the nodes to our view
	    $this->controller->set(compact('nodes'));
	    $this->controller->plugin = 'forest';
	    echo $this->controller->render('/elements/json/nodes');
	    exit;
	}

	function reorder()
	{
	    // retrieve the node instructions from javascript
	    // delta is the difference in position (1 = next node, -1 = previous node)
	    $node = intval($this->controller->params['form']['node']);
	    $delta = intval($this->controller->params['form']['delta']);

	    if ($delta > 0) {
	        $this->model->movedown($node, abs($delta));
	    } elseif ($delta < 0) {
	        $this->model->moveup($node, abs($delta));
	    }

	    // send success response
	    exit('1');
	}

	function reparent()
	{
	    $node = intval($this->controller->params['form']['node']);
	    $parent = intval($this->controller->params['form']['parent']);
	    $position = intval($this->controller->params['form']['position']);

	    // save the employee node with the new parent id
	    // this will move the employee node to the bottom of the parent list

	    $this->model->id = $node;
	    #$this->Node->Behaviors->disable('Eav');
	    $this->model->saveField('parent_id', $parent);
	    #$this->Node->Behaviors->enable('Eav');

	    // If position == 0, then we move it straight to the top
	    // otherwise we calculate the distance to move ($delta).
	    // We have to check if $delta > 0 before moving due to a bug
	    // in the tree behavior (https://trac.cakephp.org/ticket/4037)

	    if ($position == 0){
	        $this->model->moveup($node, true);
	    } else {
	        $count = $this->model->childcount($parent, true);
	        $delta = $count-$position-1;
	        if ($delta > 0){
	            $this->model->moveup($node, $delta);
	        }
	    }

	    // send success response
	    exit('1');
	}
}
?>