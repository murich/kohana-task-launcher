<?php

interface Interface_TaskLauncherTask {

	/**
	 * @return array of array('task' => 'taskName',
		'params' => array(
			'task' => 'taskName',
	        'other' => 'param'
		))
	 */
	public static function getMiniondSpecimens();
}