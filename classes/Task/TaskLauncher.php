<?php
/**
 * Helps to run all minion task we need at once. If some task not running - runs it
 *
 * Class Task_TaskLauncher
 */
class Task_TaskLauncher extends Minion_Task {

	protected $_aNeededSpecimens = NULL;
	protected $_aCurrentSpecimens = NULL;

	protected $options = array(
		'action'
	);

	protected function _execute(array $aParams) {
		$cCompare = function($aArr1, $aArr2) {
			return $aArr1['params'] == $aArr2['params'];
		};

		$aToLaunch = array_udiff($this->_getNeededSpecimens(), $this->_getCurrentSpecimens(), $cCompare);
		Minion_CLI::write('TO launch count: ' . count($aToLaunch));
		if(!$aToLaunch) {
			Minion_CLI::write('Current speciments: ' . print_r($this->_getCurrentSpecimens(), true));
		}
		foreach($aToLaunch as $aSpecimen) {
			Minion_CLI::write('Launching following task: ' . print_r($aSpecimen));
			self::_launchSpecimen($aSpecimen);
		}

		foreach(array_udiff($this->_getCurrentSpecimens(), $this->_getNeededSpecimens(), $cCompare) as $aSpecimen) {
			self::_stopSpecimen($aSpecimen);
		}
	}

	protected function _getCurrentSpecimens() {
		if($this->_aCurrentSpecimens === NULL) {
			$sProcesses = self::_getPsAuxBody();
			$this->_aCurrentSpecimens = array();
			preg_match_all('!miniond.*--task=.*!', $sProcesses, $sPsAuxLines);

			foreach($sPsAuxLines[0] as $sPsAuxLine) {
				// match options
				$aTaskParams = self::_matchParams($sPsAuxLine);
				// match if its with daemon or not
				ksort($aTaskParams);
				$this->_aCurrentSpecimens[] = array(
					'task' => $aTaskParams['task'],
					'params' => $aTaskParams,
				);
			}
		}

		return $this->_aCurrentSpecimens;
	}

	protected function _getNeededSpecimens() {
		if($this->_aNeededSpecimens === NULL) {
			$this->_aNeededSpecimens = array();
			foreach($this->_getTaskClasses() as $sClass) {
				$aClassSpecimens = array_map(function($aClassSpecimen) {
					ksort($aClassSpecimen['params']);
					return $aClassSpecimen;
				}, array_values(call_user_func($sClass . '::getMiniondSpecimens')));

				$this->_aNeededSpecimens = array_merge($this->_aNeededSpecimens, $aClassSpecimens);
			}
		}

		return $this->_aNeededSpecimens;
	}

	protected static function _getTaskClasses() {
		$aClasses = self::_getTaskClassesList(Kohana::list_files('classes/Task'));

		foreach($aClasses as $iKey => $sClass) {
			if(!in_array('Interface_TaskLauncherTask', class_implements($sClass))) {
				unset($aClasses[$iKey]);
			}
		}

		return $aClasses;
	}

	protected static  function _getPsAuxBody() {

		return shell_exec('ps aux | grep minion');
	}

	protected static function _matchParams($sPsAuxLine) {
		$aArgs = explode(' ', trim($sPsAuxLine));
		$aParams = array();
		foreach($aArgs as $sArg) {
			if(substr($sArg, 0, 2) !== '--') {
				continue;
			}
			$aArgParams = explode('=', $sArg);
			$aParams[substr($aArgParams[0], 2)] = $aArgParams[1];
		}

		return $aParams;
	}

	protected static function _launchSpecimen($aSpecimen) {
		$sParamsLine = '';
		foreach($aSpecimen['params'] as $sOption => $sValue) {
			$sParamsLine .= '--' . $sOption . '=' . $sValue . ' ';
		}

		$sCommand =  MODPATH . 'minion/miniond '. $sParamsLine .' &';

		Kohana::$log->add(Kohana_Log::INFO, 'Going to run following minion job: ' . $sCommand);
		Minion_CLI::write($sCommand);
		proc_close(proc_open($sCommand, array(), $tmp));
	}

	protected static function _stopSpecimen() {
		Minion_CLI::write('_stopSpecimen is not implemented yet. We need to built a queue in cache for it');
	}

	protected static function _getTaskClassesList(array $files, $prefix = '') {
		$output = array();

		foreach ($files as $file => $path)
		{
			$file = substr($file, strrpos($file, DIRECTORY_SEPARATOR) + 1);

			if (is_array($path) AND count($path))
			{
				$task = self::_getTaskClassesList($path, $prefix.$file.'_');

				if ($task)
				{
					$output = array_merge($output, $task);
				}
			}
			else
			{
				$output[] = 'Task_'. $prefix.substr($file, 0, -strlen(EXT));
			}
		}

		return $output;
	}
}