<?php


class ux_t3lib_install extends t3lib_install {
	function __construct() {
		die('jippi');
	}
	/**
	 * Returns an array with SQL-statements that is needed to update according to the diff-array
	 *
	 * @param	array		Array with differences of current and needed DB settings. (from getDatabaseExtra())
	 * @param	string		List of fields in diff array to take notice of.
	 * @return	array		Array of SQL statements (organized in keys depending on type)
	 */
	public function getUpdateSuggestions($diffArr, $keyList = 'extra,diff') {
		die('hook executed');

		$statements = array();
		$deletedPrefixKey = $this->deletedPrefixKey;
		$remove = 0;
		if ($keyList == 'remove') {
			$remove = 1;
			$keyList = 'extra';
		}
		$keyList = explode(',', $keyList);
		foreach ($keyList as $theKey) {
			if (is_array($diffArr[$theKey])) {
				foreach ($diffArr[$theKey] as $table => $info) {
					$whole_table = array();
					if (is_array($info['fields'])) {
						foreach ($info['fields'] as $fN => $fV) {
							if ($info['whole_table']) {
								$whole_table[] = '`' . $fN . '` ' . $fV;
							} else {
									// Special case to work around MySQL problems when adding auto_increment fields:
								if (stristr($fV, 'auto_increment')) {
										// The field can only be set "auto_increment" if there exists a PRIMARY key of that field already.
										// The check does not look up which field is primary but just assumes it must be the field with the auto_increment value...
									if (isset($diffArr['extra'][$table]['keys']['PRIMARY'])) {
											// Remove "auto_increment" from the statement - it will be suggested in a 2nd step after the primary key was created
										$fV = str_replace(' auto_increment', '', $fV);
									} else {
											// In the next step, attempt to clear the table once again (2 = force)
										$info['extra']['CLEAR'] = 2;
									}
								}
								if ($theKey == 'extra') {
									if ($remove) {
										if (substr($fN, 0, strlen($deletedPrefixKey)) != $deletedPrefixKey) {
											$statement = 'ALTER TABLE `' . $table . '` CHANGE `' . $fN . '` `' . $deletedPrefixKey . $fN . '` ' . $fV . ';';
											$statements['change'][md5($statement)] = $statement;
										} else {
											$statement = 'ALTER TABLE ' . $table . ' DROP `' . $fN . '`;';
											$statements['drop'][md5($statement)] = $statement;
										}
									} else {
										$statement = 'ALTER TABLE ' . $table . ' ADD `' . $fN . '` ' . $fV . ';';
										$statements['add'][md5($statement)] = $statement;
									}
								} elseif ($theKey == 'diff') {
									$statement = 'ALTER TABLE ' . $table . ' CHANGE `' . $fN . '` `' . $fN . '` ' . $fV . ';';
									$statements['change'][md5($statement)] = $statement;
									$statements['change_currentValue'][md5($statement)] = $diffArr['diff_currentValues'][$table]['fields'][$fN];
								}
							}
						}
					}
					if (is_array($info['keys'])) {
						foreach ($info['keys'] as $fN => $fV) {
							if ($info['whole_table']) {
								$whole_table[] = $fV;
							} else {
								if ($theKey == 'extra') {
									if ($remove) {
										$statement = 'ALTER TABLE `' . $table . '`' . ($fN == 'PRIMARY' ? ' DROP PRIMARY KEY' : ' DROP KEY `' . $fN . '`') . ';';
										$statements['drop'][md5($statement)] = $statement;
									} else {
										$statement = 'ALTER TABLE `' . $table . '` ADD ' . $fV . ';';
										$statements['add'][md5($statement)] = $statement;
									}
								} elseif ($theKey == 'diff') {
									$statement = 'ALTER TABLE `' . $table . '`' . ($fN == 'PRIMARY' ? ' DROP PRIMARY KEY' : ' DROP KEY `' . $fN . '`') . ';';
									$statements['change'][md5($statement)] = $statement;
									$statement = 'ALTER TABLE ' . $table . ' ADD ' . $fV . ';';
									$statements['change'][md5($statement)] = $statement;
								}
							}
						}
					}
					if (is_array($info['extra'])) {
						$extras = array();
						$extras_currentValue = array();
						$clear_table = FALSE;

						foreach ($info['extra'] as $fN => $fV) {

								// Only consider statements which are missing in the database but don't remove existing properties
							if (!$remove) {
								if (!$info['whole_table']) { // If the whole table is created at once, we take care of this later by imploding all elements of $info['extra']
									if ($fN == 'CLEAR') {
											// Truncate table must happen later, not now
											// Valid values for CLEAR: 1=only clear if keys are missing, 2=clear anyway (force)
										if (count($info['keys']) || $fV == 2) {
											$clear_table = TRUE;
										}
										continue;
									} else {
										$extras[] = $fN . '=' . $fV;
										$extras_currentValue[] = $fN . '=' . $diffArr['diff_currentValues'][$table]['extra'][$fN];
									}
								}
							}
						}
						if ($clear_table) {
							$statement = 'TRUNCATE TABLE `' . $table . '`;';
							$statements['clear_table'][md5($statement)] = $statement;
						}
						if (count($extras)) {
							$statement = 'ALTER TABLE `' . $table . '` ' . implode(' ', $extras) . ';';
							$statements['change'][md5($statement)] = $statement;
							$statements['change_currentValue'][md5($statement)] = implode(' ', $extras_currentValue);
						}
					}
					if ($info['whole_table']) {
						if ($remove) {
							if (substr($table, 0, strlen($deletedPrefixKey)) != $deletedPrefixKey) {
								$statement = 'ALTER TABLE `' . $table . '` RENAME `' . $deletedPrefixKey . $table . '`;';
								$statements['change_table'][md5($statement)] = $statement;
							} else {
								$statement = 'DROP TABLE `' . $table . '`;';
								$statements['drop_table'][md5($statement)] = $statement;
							}
								// count:
							$count = $GLOBALS['TYPO3_DB']->exec_SELECTcountRows('*', $table);
							$statements['tables_count'][md5($statement)] = $count ? 'Records in table: ' . $count : '';
						} else {
							$statement = 'CREATE TABLE `' . $table . "` (\n" . implode(",\n", $whole_table) . "\n)";
							if ($info['extra']) {
								foreach ($info['extra'] as $k => $v) {
									if ($k == 'COLLATE' || $k == 'CLEAR') {
										continue; // Skip these special statements. TODO: collation support is currently disabled (needs more testing)
									}
									$statement .= ' ' . $k . '=' . $v; // Add extra attributes like ENGINE, CHARSET, etc.
								}
							}
							$statement .= ';';
							$statements['create_table'][md5($statement)] = $statement;
						}
					}
				}
			}
		}
		return $statements;
	}
}