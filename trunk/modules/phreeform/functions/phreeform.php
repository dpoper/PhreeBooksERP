<?php
// +-----------------------------------------------------------------+
// |                   PhreeBooks Open Source ERP                    |
// +-----------------------------------------------------------------+
// | Copyright(c) 2008-2015 PhreeSoft      (www.PhreeSoft.com)       |
// +-----------------------------------------------------------------+
// | This program is free software: you can redistribute it and/or   |
// | modify it under the terms of the GNU General Public License as  |
// | published by the Free Software Foundation, either version 3 of  |
// | the License, or any later version.                              |
// |                                                                 |
// | This program is distributed in the hope that it will be useful, |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of  |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the   |
// | GNU General Public License for more details.                    |
// +-----------------------------------------------------------------+
//  Path: /modules/phreeform/functions/phreeform.php
//

function default_report() {
  $report = new \core\classes\objectInfo();
  $report->page->margin->top    = PF_DEFAULT_MARGIN;
  $report->page->margin->bottom = PF_DEFAULT_MARGIN;
  $report->page->margin->left   = PF_DEFAULT_MARGIN;
  $report->page->margin->right  = PF_DEFAULT_MARGIN;
  $report->page->title1->text   = PF_DEFAULT_TITLE1;
  $report->page->title2->text   = PF_DEFAULT_TITLE2;
  $report->page->size           = PF_DEFAULT_PAPERSIZE;
  $report->page->orientation    = PF_DEFAULT_ORIENTATION;
  $report->security             = 'u:0;g:0';
  return $report;
}

function get_mime_image($ext, $folder = false) {
	if ($folder) return 'places/folder.png';
	switch ($ext) {
		case 'doc':  return 'mimetypes/x-office-document.png';
		case 'xls':  return 'mimetypes/x-office-spreadsheet.png';
		case 'ppt':  return 'mimetypes/x-office-presentation.png';
		case 'drw':
		case 'png':
		case 'gif':
		case 'jpg':
		case 'jpeg': return 'mimetypes/image-x-generic.png';
		case 'htm':
		case 'html': return 'mimetypes/text-html.png';
		case 'pdf':  return 'phreebooks/pdficon_small.gif';
		case 'txt':
		default:     return 'mimetypes/text-x-generic.png';
	}
}

function convertPfColor($color = '0:0:0') {
  $colors   = explode(':', $color);
  $hexcolor = str_pad(dechex($colors[0]), 2, STR_PAD_LEFT) .
              str_pad(dechex($colors[1]), 2, STR_PAD_LEFT) .
			  str_pad(dechex($colors[2]), 2, STR_PAD_LEFT);
  return $hexcolor;
}

function buildToggleList($id, $toggle_list = '') {
  global $admin;
  $result = $admin->DataBase->query("select parent_id from " . TABLE_PHREEFORM . " where id = '" . $id . "'");
  if ($result->fields['parent_id'] <> '0') $toggle_list = buildToggleList($result->fields['parent_id'], $toggle_list);
  $toggle_list .= "  Toggle('dc_" . $result->fields['parent_id'] . "_" . $id . "');" . chr(10);
  return $toggle_list;
}

function security_check($tokens) {
  $categories = explode(';', $tokens);
  $user_str = ':' . $_SESSION['user']->admin_id . ':';
  $emp_str  = ':' . ($_SESSION['user']->account_id ? $_SESSION['user']->account_id : '0') . ':';
  $dept_str = ':' . ($_SESSION['user']->department ? $_SESSION['user']->department : '0') . ':';
  foreach ($categories as $category) {
	$type = substr($category, 0, 1);
	$approved_ids = substr($category, 1) . ':';
	if (strpos($approved_ids, ':0:') !== false) return true; // for 'all' field
	switch ($type) {
	  case 'u': if (strpos($approved_ids, $user_str) !== false) return true; break;
	  case 'e': if (strpos($approved_ids, $emp_str)  !== false) return true; break;
	  case 'd': if (strpos($approved_ids, $dept_str) !== false) return true; break;
	}
  }
  return false;
}

function pf_validate_security($security = 'u:-1;g:-1', $include_all = true) {
	$types    = explode(';', $security);
	$settings = array();
	foreach ($types as $value) {
	  $temp   = explode(':', $value);
	  $type   = array_shift($temp);
	  $settings[$type] = $temp;
	}
	if (!is_array($settings['u']) || !is_array($settings['g'])) return false;
	if (in_array($_SESSION['user']->admin_id, $settings['u']) || in_array($_SESSION['user']->department, $settings['g'])) return true;
	if ($include_all && (in_array(0, $settings['u']) || in_array(0, $settings['g']))) return true;
	return false;
}

function build_groups() { // dynamically build report and form groups
  global $admin;
  $output = array();
  $result = $admin->DataBase->query("select doc_ext, doc_group, doc_title from " . TABLE_PHREEFORM . " where doc_ext in ('ff','0')");
  while (!$result->EOF) {
    switch ($result->fields['doc_ext']) {
    	case 'ff': $output['forms'][$result->fields['doc_group']]   = $result->fields['doc_title']; break;
    	case '0': $output['reports'][$result->fields['doc_group']] = $result->fields['doc_title']; break;
	}
	$result->MoveNext();
  }
  return $output;
}

function load_recently_added() {
	global $admin;
	$contents = NULL;
	$sql = "select id, security, doc_title from " . TABLE_PHREEFORM . " where doc_type <> '0' order by create_date desc, id desc limit 20";
	$result = $admin->DataBase->query($sql);
	if ($result->fetch(\PDO::FETCH_NUM) == 0) {
	  $contents .= TEXT_NO_DOCUMENTS_HAVE_BEEN_FOUND . '<br />';
	} else {
	  while (!$result->EOF) {
	    if (pf_validate_security($result->fields['security'], true)) {
		  $contents .= '  <div>';
		  $contents .= '    <a href="javascript:fetch_doc(' . $result->fields['id'] . ');">';
		  $contents .= html_icon('mimetypes/text-x-generic.png', $result->fields['doc_title'], 'small') . ' ';
		  $contents .= $result->fields['doc_title'] . '</a>';
		  $contents .= '  </div>' . chr(10);
	    }
		$result->MoveNext();
	  }
	}
	return $contents;
}

function load_my_reports() {
	global $admin;
	$contents = NULL;
	$sql = "select id, doc_title, security from " . TABLE_PHREEFORM . "
	  where doc_type <> '0' order by doc_title";
	$result = $admin->DataBase->query($sql);
	if ($result->fetch(\PDO::FETCH_NUM) == 0) {
	  $contents .= TEXT_NO_DOCUMENTS_HAVE_BEEN_FOUND . '<br />';
	} else {
	  while (!$result->EOF) {
		if (pf_validate_security($result->fields['security'], false)) {
		  $contents .= '  <div>';
		  $contents .= '    <a href="javascript:fetch_doc(' . $result->fields['id'] . ');">';
		  $contents .= html_icon('mimetypes/text-x-generic.png', $result->fields['doc_title'], 'small') . ' ';
		  $contents .= $result->fields['doc_title'] . '</a>';
		  $contents .= '  </div>' . chr(10);
	    }
		$result->MoveNext();
	  }
	}
	return $contents;
}

function find_special_class($class_name) {
	global $admin;
  	foreach ($admin->classes as $key => $class) {
    	if (file_exists(DIR_FS_MODULES . $key . '/classes/' . $class_name . '.php')) return DIR_FS_MODULES . $key . '/';
  	}
  	throw new \core\classes\userException("Special class: $class_name was called but could not be found!");
}

function load_special_language($path, $class_name) {
  	if (file_exists($path . "language/{$_SESSION['user']->language}/classes/$class_name.php")) {
    	require_once ($path . "language/{$_SESSION['user']->language}/classes/$class_name.php");
  	} elseif (file_exists($path . "language/en_us/classes/$class_name.php")) {
    	require_once       ($path . "language/en_us/classes/$class_name.php");
  	}
}

function ReadDefReports($name, $path) {
  if (!$dh = @opendir(DIR_FS_ADMIN . $path)) return TEXT_NO_DOCUMENTS_HAVE_BEEN_FOUND;
  $i = 0;
  while ($DefRpt = readdir($dh)) {
	$pinfo = pathinfo(DIR_FS_ADMIN . $path . '/' . $DefRpt);
	switch ($pinfo['extension']) {
	  case 'txt':
		$FileLines = file(DIR_FS_ADMIN . $path . '/' . $DefRpt);
		foreach ($FileLines as $OneLine) { // find the main reports sql statement, language and execute it
		  if (strpos($OneLine, 'ReportNarr:') === 0) $ReportNarr = substr(trim($OneLine), 12, -1);
		  if (strpos($OneLine, 'ReportData:') === 0) { // then it's the line we're after with description and groupname
			$GrpPos = strpos($OneLine, "groupname='") + 11;
			$GrpName = substr($OneLine, $GrpPos, strpos($OneLine, "',", $GrpPos) - $GrpPos);
			$RptPos = strpos($OneLine,"description='") + 13;
			$RptName = substr($OneLine, $RptPos, strpos($OneLine, "',", $RptPos) - $RptPos);
			$ReportList[$GrpName][$i]['RptName']  = $RptName;
			$ReportList[$GrpName][$i]['RptNarr']  = $ReportNarr;
			$ReportList[$GrpName][$i]['FileName'] = $pinfo[basename];
			$i++;
		  }
		}
		break;
	  case 'xml':
		if (($data = @file_get_contents(DIR_FS_ADMIN . $path . '/' . $DefRpt)) === false) throw new \core\classes\userException(sprintf(ERROR_READ_FILE, DIR_FS_ADMIN . $path . '/' . $DefRpt));
		$report = xml_to_object($data);
		if (is_object($report->PhreeformReport)) $report = $report->PhreeformReport; // remove container tag
		$ReportList[$report->groupname][$i]['RptName']  = $report->title;
		$ReportList[$report->groupname][$i]['RptNarr']  = $report->description;
		$ReportList[$report->groupname][$i]['FileName'] = $pinfo[basename];
		$i++;
	    break;
	  default: continue; // not a report
	}
  }
  closedir($dh);
  $OptionList  = '<select name="' . $name . '" size="15">';
  $LstGroup    = '';
  $CloseOptGrp = false;
  $i           = 0;
  if (is_array($ReportList)) {
    $groups = build_groups();
    ksort($ReportList);
    foreach ($ReportList as $GrpName => $members) {
	  $group_split = explode(':', $GrpName); // if it's a form then remove the report from category id
	  $GrpMember = $groups['reports'][$group_split[0]];
	  if (!$GrpMember) $GrpMember = TEXT_MISCELLANEOUS;
	  if (isset($group_split[1])) {
	    $form_group = $groups['forms'][$GrpName];
	    if (!$form_group) $form_group = TEXT_UNCATEGORIZED_FORM;
	    $label = $GrpMember . ' - ' . TEXT_FORMS . ' - ' . $form_group;
	  } else {
	    $label = $GrpMember . ' - ' . TEXT_REPORTS;
	  }
	  $OptionList .= '<optgroup label="' . $label . '" title="' . $GrpName . '">';
	  foreach ($members as $Temp) {
		$OptionList .= '<option value="' . $Temp['FileName'] . '">' . htmlspecialchars($Temp['RptName'] . ' - ' . truncate_string($Temp['RptNarr'], $len = 65)) . '</option>';
	  }
	  $OptionList .= '</optgroup>';
    }
  }
  return $OptionList . '</select>';
}

function get_report_details($id) {
  if (!$id) throw new \core\classes\userException("There was no report or form passed to open!");
  $filename = PF_DIR_MY_REPORTS . 'pf_' . $id;
  if (!file_exists($filename)) 								throw new \core\classes\userException("The report or form '$filename' could not be found in the my_reports directory!");
  if (!$handle = @fopen($filename, "r")) 					throw new \core\classes\userException(sprintf(ERROR_ACCESSING_FILE, $filename));
  if (!$contents = @fread($handle, filesize($filename)))	throw new \core\classes\userException(sprintf(ERROR_READ_FILE,		$filename));
  if (!@fclose($handle))									throw new \core\classes\userException(sprintf(ERROR_CLOSING_FILE, 	$filename));
  if (!$report = xml_to_object($contents)) return false;
  if (is_object($report->PhreeformReport)) $report = $report->PhreeformReport; // remove container tag
  // fix some special cases
  if (isset($report->tables)     && is_object($report->tables))     $report->tables     = array($report->tables);
  if (isset($report->grouplist)  && is_object($report->grouplist))  $report->grouplist  = array($report->grouplist);
  if (isset($report->sortlist)   && is_object($report->sortlist))   $report->sortlist   = array($report->sortlist);
  if (isset($report->filterlist) && is_object($report->filterlist)) $report->filterlist = array($report->filterlist);
  if (isset($report->fieldlist)  && is_object($report->fieldlist))  $report->fieldlist  = array($report->fieldlist);
  if (is_array($report->fieldlist)) foreach ($report->fieldlist as $key => $value) {
	if (is_object($value->boxfield)) $report->fieldlist[$key]->boxfield = array($value->boxfield);
  }
  return $report;
}

function ImportReport($RptName = '', $RptFileName = '', $import_path = PF_DIR_DEF_REPORTS, $save_path = PF_DIR_MY_REPORTS) {
	global $admin;
	$rID = '';
	if ($RptFileName <> '') { // then a locally stored report was chosen
	  	$path = $import_path . $RptFileName;
		validate_upload('reportfile');
	  	$path = $_FILES['reportfile']['tmp_name'];
	} else {
	  	throw new \core\classes\userException(PHREEFORM_IMPORT_ERROR);
	}
	if (!$handle = @fopen($path, "r")) 					throw new \core\classes\userException(sprintf(ERROR_ACCESSING_FILE, $path));
	if (!$contents = @fread($handle, filesize($path))) 	throw new \core\classes\userException(sprintf(ERROR_READ_FILE,  	$path));
	if (!@fclose($handle)) 								throw new \core\classes\userException(sprintf(ERROR_CLOSING_FILE, 	$path));
	if (strpos($contents, 'Report Builder Export Tool')) { // it's an old style report
	  require_once(DIR_FS_MODULES . 'phreeform/functions/reportwriter.php');
	  $report = import_text_params(file($path));
	} else { // assume it's a new xml type
	  if (!$report = xml_to_object($contents)) throw new \core\classes\userException ("the file is empty");
	  if (is_object($report->PhreeformReport)) $report = $report->PhreeformReport; // remove container tag
	}
	if ($RptName <> '') $report->title = $RptName; // replace the title if provided
	// error check
	$result = $admin->DataBase->query("select id from " . TABLE_PHREEFORM . "
	  where doc_title = '" . addslashes($report->title) . "' and doc_type <> '0'");
	if ($result->fetch(\PDO::FETCH_NUM) > 0) { // the report name already exists, if file exists error, else write
	  	$rID = $result->fields['id'];
	  	// file exists - error and return
	  	if (file_exists($save_path . 'pf_' . $rID)) throw new \core\classes\userException (sprintf(PHREEFORM_REPDUP, $report->title));
	}
	save_report($report, $rID, $save_path);
}

function save_report($report, $rID = '', $save_path = PF_DIR_MY_REPORTS) {
	global $admin;
	$output  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . chr(10);
	$output .= '<PhreeformReport>' . chr(10);
	$output .= object_to_xml($report);
	$output .= '</PhreeformReport>' . chr(10);
//echo 'xml output = ' . str_replace(chr(10) , "<br>", htmlspecialchars($output)) . '<br>'; exit();
	// see if a folder exists with the group to put it in
	$result = $admin->DataBase->query("select id from " . TABLE_PHREEFORM . "
	  where doc_group = '" . $report->groupname . "' and doc_type = '0' and doc_ext in ('ff', 'fr')");
	if ($result->fetch(\PDO::FETCH_NUM) == 0) {
	  if ($report->reporttype == 'frm') {
	    $result = $admin->DataBase->query("select id from " . TABLE_PHREEFORM . "
	      where doc_group = 'misc:misc' and doc_type = '0'");
	  } else {
	    $result = $admin->DataBase->query("select id from " . TABLE_PHREEFORM . "
	      where doc_group = 'misc' and doc_type = '0'");
	  }
	}
	$parent_id = $result->fields['id'];
	if ($report->standard_report == '1') $report->standard_report = 's';
	$sql_array = array(
	  'parent_id' => $parent_id,
	  'doc_type'  => isset($report->standard_report) ? $report->standard_report : 's',
	  'doc_title' => $report->title,
	  'doc_group' => $report->groupname,
	  'doc_ext'   => $report->reporttype,
	  'security'  => $report->security,
	);
	if ($rID) { // update
	  $sql_array['last_update'] = date('Y-m-d');
	  db_perform(TABLE_PHREEFORM, $sql_array, 'update', 'id = ' . $rID);
	} else { // add
	  $sql_array['create_date'] = date('Y-m-d');
	  db_perform(TABLE_PHREEFORM, $sql_array);
	  $rID = \core\classes\PDO::lastInsertId('id');
	}
	$filename = $save_path . 'pf_' . $rID;
	if (!$handle = @fopen($filename, 'w'))	throw new \core\classes\userException(sprintf(ERROR_ACCESSING_FILE, 	$filename));
	if (!@fwrite($handle, $output))			throw new \core\classes\userException(sprintf(ERROR_WRITE_FILE, 	$filename));
	if (!@fclose($handle))					throw new \core\classes\userException(sprintf(ERROR_CLOSING_FILE, 		$filename));
	return $rID;
}

function truncate_string($str, $len = 32) {
  if (strlen($str) > $len) {
    return substr($str, 0, $len - 3) . '...';
  } else {
    return $str;
  }
}

function build_dir_path($id) {
	global $admin;
	$result = $admin->DataBase->query("select parent_id, doc_title from " . TABLE_PHREEFORM . " where id = '" . $id . "'");
	$title  = ($id) ? $result->fields['doc_title'] : '';
	if ($result->fields['parent_id']) $title = build_dir_path($result->fields['parent_id']) . '/' . $title;
	return $title;
}

function validate_dir_move($dir_tree, $id, $new_parent) {
	if ($id <> 0 && $new_parent == $id) return false;
	if ($dir_tree[$new_parent] <> 0) return validate_dir_move($dir_tree, $id, $dir_tree[$new_parent]);
	return true;
}

function BuildForm($report, $delivery_method = 'D') { // for forms only
	global $admin, $messageStack, $FieldValues;
	$output = array();

	// check for at least one field selected to show
	// No fields are checked to show, that's bad
	if (!$report->fieldlist) throw new \core\classes\userException(PHREEFORM_NOROWS);
	// Let's build the sql field list for the general data fields (not totals, blocks or tables)
	$strField = array();
	foreach ($report->fieldlist as $key => $field) { // check for a data field and build sql field list
	  if (in_array($field->type, array('Data','BarCode','ImgLink'))) { // then it's data field make sure it's not empty
		if ($field->boxfield[0]->fieldname) {
		  $strField[] = prefixTables($field->boxfield[0]->fieldname) . ' as d' . $key;
		} else { // the field is empty, bad news, error and exit
		  throw new \core\classes\userException(PHREEFORM_EMPTYFIELD . $key);
		}
	  }
	}
	$report->sqlField  = implode(', ', $strField);
	// fetch the sort order and add to group by string to finish ORDER BY string
	$strSort = array();
	if (is_array($report->sortlist)) foreach ($report->sortlist as $sortline) {
	  if ($sortline->default == '1') $strSort[] = prefixTables($sortline->fieldname);
	}
	$sqlSort   = implode(', ', $strSort);
	// fetch criteria and date filter info
    $strCrit = array();
	if ($report->datedefault) {
		$tempDate = new \core\classes\DateTime();
	  	$dates = $tempDate->sql_date_array($report->datedefault, prefixTables($report->datefield));
	  	if ($dates['sql']) $strCrit[] = $dates['sql'];
	}
//echo 'report = '; print_r($report); echo '<br />';
	$criteria  = build_criteria($report);
	if ($criteria['sql']) $strCrit[] = $criteria['sql'];
	$report->sqlCrit   = implode(' and ', $strCrit);
	// fetch the tables to query
	$report->sqlTable = '';
	foreach ($report->tables as $table) {
	  if (isset($table->relationship)) {
	    if (!$table->joinopt) $table->joinopt = ' JOIN ';
	    $report->sqlTable .= ' ' . $table->joinopt . ' ' . DB_PREFIX . $table->tablename . ' ON ' . prefixTables($table->relationship);
	  } else {
	    $report->sqlTable .= DB_PREFIX . $table->tablename;
	  }
	}
	// We now have the sql, find out how many groups in the query (to determine the number of forms)
	$form_field_list = ($report->filenamefield == '') ? (prefixTables($report->formbreakfield)) : (prefixTables($report->formbreakfield) . ', ' . prefixTables($report->filenamefield));
	$sql = 'select ' . $form_field_list . ' from ' . $report->sqlTable;
	if ($report->sqlCrit) $sql .= ' where ' . $report->sqlCrit;
	$sql .= ' group by ' . prefixTables($report->formbreakfield);
	if ($strSort) $sql .= ' order by ' . $sqlSort;
	// execute sql to see if we have data
//echo 'sql = ' . $sql . '<br />'; exit();
	$result = $admin->DataBase->query($sql);
	if (!$result->fetch(\PDO::FETCH_NUM)) throw new \core\classes\userException(PHREEFORM_NOROWS);

	// set the filename for download or email
	if ($report->filenameprefix || $report->filenamefield) {
	  $report->filename = $report->filenameprefix . $result->fields[strip_tablename($report->filenamefield)] . '.pdf';
	} else {
	  $report->filename = ReplaceNonAllowedCharacters($report->title) . '.pdf';
	}
	// create an array for each form
	$report->recordID = array();
	while (!$result->EOF) {
	  $report->recordID[] = $result->fields[strip_tablename($report->formbreakfield)];
	  $result->MoveNext();
	}
	// retrieve the company information
	for ($i = 0; $i < sizeof($report->fieldlist); $i++) {
	  	if ($report->fieldlist[$i]->type == 'CDta') {
			$report->fieldlist[$i]->text = constant($report->fieldlist[$i]->boxfield[0]->fieldname);
	  	} else if ($report->fieldlist[$i]->type == 'CBlk') {
			if (!$report->fieldlist[$i]->boxfield) throw new \core\classes\userException(PHREEFORM_EMPTYFIELD . $report->fieldlist[$i]->description);
			$TextField = '';
			foreach ($report->fieldlist[$i]->boxfield as $entry) {
				$temp = $entry->formatting ? ProcessData(constant($entry->fieldname), $entry->formatting) : constant($entry->fieldname);
				$TextField .= AddSep($temp, $entry->processing);
			}
			$report->fieldlist[$i]->text = $TextField;
	  	}
	}
	// patch for special_reports (forms) where the data file is generated externally from the standard class
	if ($report->special_class) {
      	$path = find_special_class($report->special_class);
      	load_special_language($path, $report->special_class);
      	require_once($path . '/classes/' . $report->special_class . '.php');
	}
    if ($report->serialform) {
	  $output = BuildSeq($report, $delivery_method); // build sequential form (receipt style)
    } else {
	  $output = BuildPDF($report, $delivery_method); // build standard PDF form, doesn't return if download
    }
	return $output; //if we are here, return the contents for an email attachment
}

function BuildPDF($report, $delivery_method = 'D') { // for forms only - PDF style
	global $admin, $messageStack, $FieldValues, $posted_currencies;
	// Generate a form for each group element
	$output = array();
	$pdf    = new \phreeform\classes\form_generator();
	foreach ($report->recordID as $formNum => $Fvalue) {
	  $pdf->StartPageGroup();
	  // find the single line data from the query for the current form page
	  $TrailingSQL = " from " . $report->sqlTable . " where " . ($report->sqlCrit ? $report->sqlCrit . " AND " : '') . prefixTables($report->formbreakfield) . " = '" . $Fvalue . "'";
	  $FieldValues = array();
	  if ($report->special_class) {
	    $form_class   = $report->special_class;
		$special_form = new $form_class();//@todo needs new location
		$FieldValues  = $special_form->load_query_results($report->formbreakfield, $Fvalue);
	  } else {
		if (strlen($report->sqlField) > 0) {
//echo 'sql = select ' . $report->sqlField . $TrailingSQL . '<br />'; exit();
		  $result      = $admin->DataBase->query("select " . $report->sqlField . $TrailingSQL);
		  $FieldValues = $result->fields;
		}
	  }
	  // load the posted currency values
	  $posted_currencies = array();
	  if (ENABLE_MULTI_CURRENCY && strpos($report->sqlTable, TABLE_JOURNAL_MAIN) !== false) {
	    $sql    = "select currencies_code, currencies_value " . $TrailingSQL;
	    $result = $admin->DataBase->query($sql);
	    $posted_currencies = array(
		  'currencies_code'  => $result->fields['currencies_code'],
		  'currencies_value' => $result->fields['currencies_value'],
	    );
	  } else {
	    $posted_currencies = array('currencies_code' => DEFAULT_CURRENCY, 'currencies_value' => 1);
	  }
	  foreach ($report->fieldlist as $key => $field) { // Build the text block strings
	    if ($field->type == 'TBlk') {
		  if (!$field->boxfield[0]->fieldname) {
		    throw new \core\classes\userException(PHREEFORM_EMPTYFIELD . $field->fieldname);
		  }
		  if ($report->special_class) {
		    $TextField = $special_form->load_text_block_data($field->boxfield);
		  } else {
		    $arrTxtBlk = array(); // Build the fieldlist
		    foreach ($field->boxfield as $idx => $entry) $arrTxtBlk[] = prefixTables($entry->fieldname) . ' as r' . $idx;
		    $strTxtBlk = implode(', ', $arrTxtBlk);
		    $result    = $admin->DataBase->query("select " . $strTxtBlk . $TrailingSQL);
		    $TextField = '';
		    for ($i = 0; $i < sizeof($field->boxfield); $i++) {
		      $temp = $field->boxfield[$i]->formatting ? ProcessData($result->fields['r'.$i], $field->boxfield[$i]->formatting) : $result->fields['r'.$i];
		      $TextField .= AddSep($temp, $field->boxfield[$i]->processing);
		    }
		  }
		  $report->fieldlist[$key]->text = $TextField;
	    }
	    if ($field->type == 'LtrData') { // letter template
		  if (!$field->boxfield) {
		    throw new \core\classes\userException(PHREEFORM_EMPTYFIELD . $field->description);
		  }
		  $tblField = '';
		  $data = array();
		  if ($report->special_class) {
		    $report->LetterData = $special_form->load_letter_data($field->boxfield);
		  } else {
		    $tblField = array();
			foreach ($field->boxfield as $key => $TableField) $tblField[] = prefixTables($TableField->fieldname) . ' as `' . $TableField->description . '`';
			$tblField = implode(', ', $tblField);
			$result = $admin->DataBase->query("select " . $tblField . $TrailingSQL . " LIMIT 1");
		  	foreach ($field->boxfield as $key => $TableField) {
		      if ($TableField->processing) $result->fields[$TableField->description] = ProcessData($result->fields[$TableField->description], $TableField->processing);
		    }
			$report->LetterData = $result->fields;
		  }
	    }
	    // Pre-load all total fields with 'Continued' label for multipage
	    if ($field->type == 'Ttl') $report->fieldlist[$key]->text = TEXT_CONTINUED;
	  }
	  $pdf->PageCnt = $pdf->PageNo(); // reset the current page numbering for this new form
	  $pdf->AddPage();
	  // Send the table
	  foreach ($report->fieldlist as $TableObject) {
	    if ($TableObject->type == 'Tbl') {
		  if (!$TableObject->boxfield) throw new \core\classes\userException(PHREEFORM_EMPTYFIELD . $TableObject->description);
		  // Build the sql
		  $tblField = '';
		  $tblHeading = array();
		  foreach ($TableObject->boxfield as $TableField) $tblHeading[] = $TableField->description;
		  $data = array();
		  if ($report->special_class) {
		    $data = $special_form->load_table_data($TableObject->boxfield);
		  } else {
		    $tblField = array();
			  foreach ($TableObject->boxfield as $key => $TableField) $tblField[] = prefixTables($TableField->fieldname) . ' as r' . $key;
			  $tblField = implode(', ', $tblField);
			  $result = $admin->DataBase->query("select " . $tblField . $TrailingSQL);
			  while (!$result->EOF) {
				$data[] = $result->fields;
				$result->MoveNext();
			  }
		  }
		  array_unshift($data, $tblHeading); // set the first data element to the headings
		  $TableObject->data = $data;
		  $StoredTable = clone $TableObject;
		  $pdf->FormTable($TableObject);
	    }
	  }
	  // Send the duplicate data table (only works if each form is contained in a single page [no multi-page])
	  foreach ($report->fieldlist as $field) {
	    if ($field->type == 'TDup') {
		  if (!$StoredTable) throw new \core\classes\userException(PHREEFORM_EMPTYTABLE . $field->description);
		  // insert new coordinates into existing table
		  $StoredTable->abscissa = $field->abscissa;
		  $StoredTable->ordinate = $field->ordinate;
		  $pdf->FormTable($StoredTable);
	    }
	  }
	  foreach ($report->fieldlist as $key => $field) {
	    // Set the totals (need to be on last printed page) - Handled in the Footer function in FPDF
	    if ($field->type == 'Ttl') {
		  if (!$field->boxfield) throw new \core\classes\userException(PHREEFORM_EMPTYFIELD . $field->description);
		  $report->fieldlist[$key]->processing = $field->boxfield[0]->processing; // assume first processing setting carries for the total
		  if ($report->special_class) {
		    $FieldValues = $special_form->load_total_results($field);
		  } else {
		    $ttlField = '';
		    foreach ($field->boxfield as $TotalField) $ttlField[] = prefixTables($TotalField->fieldname);
		    $sql         = "select sum(" . implode(' + ', $ttlField) . ") as form_total" . $TrailingSQL;
		    $result      = $admin->DataBase->query($sql);
		    $FieldValues = $result->fields['form_total'];
		  }
		  $report->fieldlist[$key]->text = $FieldValues;
	    }
	    // Set the data for the last Page if last page only flag checked, pull from temp save
	    if ($field->type == 'Data' && $field->display == '2') {
		  $report->fieldlist[$key]->text = $report->fieldlist[$key]->texttemp;
	    }
	  }
	  // set the printed flag field if provided
	  if ($report->setprintedflag) {
		$id_field = $report->formbreakfield;
		$temp     = explode('.', $report->setprintedflag);
		if (sizeof($temp) == 2) { // need the table name and field name
		  $sql = "update " . $temp[0] . " set " . $temp[1] . " = " . $temp[1] . " + 1 where " . $report->formbreakfield . " = '" . $Fvalue . "'";
		  $admin->DataBase->query($sql);
		}
	  }
	}
	// Add additional headers needed for MSIE and send page
	header_remove();
	header('Pragma: cache');
	header('Cache-Control: public, must-revalidate, max-age=0');
	$output['filename'] = $report->filename;
	$output['pdf']      = $pdf->Output($report->filename, $delivery_method);
	if ($delivery_method == 'S') return $output;
	exit(); // needs to be here to properly render the pdf file if delivery_method = I or D
}

function BuildSeq($report, $delivery_method = 'D') { // for forms only - Sequential mode
  global $admin, $messageStack, $FieldValues, $posted_currencies;
  // Generate a form for each group element
  $output = NULL;
  foreach ($report->recordID as $formNum => $Fvalue) {
    // find the single line data from the query for the current form page
    $TrailingSQL = " from " . $report->sqlTable . " where " . ($report->sqlCrit ? $report->sqlCrit . " AND " : '') . prefixTables($report->formbreakfield) . " = '" . $Fvalue . "'";
    if ($report->special_class) {
	  $form_class   = $report->special_class;
	  $special_form = new $form_class();//@todo needs new location
	  $FieldValues  = $special_form->load_query_results($report->formbreakfield, $Fvalue);
    } else {
	  $result       = $admin->DataBase->query("select " . $report->sqlField . $TrailingSQL);
	  $FieldValues  = $result->fields;
    }
    // load the posted currency values
    $posted_currencies = array();
    if (ENABLE_MULTI_CURRENCY && strpos($report->sqlTable, TABLE_JOURNAL_MAIN) !== false) {
	  $sql    = "select currencies_code, currencies_value " . $TrailingSQL;
	  $result = $admin->DataBase->query($sql);
	  $posted_currencies = array(
	    'currencies_code'  => $result->fields['currencies_code'],
	    'currencies_value' => $result->fields['currencies_value'],
	  );
    } else {
	  $posted_currencies = array('currencies_code' => DEFAULT_CURRENCY, 'currencies_value' => 1);
    }
    foreach ($report->fieldlist as $key => $field) {
	  switch ($field->type) {
		default:
		  $oneline = formatReceipt($field->text, $field->width, $field->align, $oneline);
		  break;
		case 'Data':
		  $value   = ProcessData(array_shift($FieldValues), $field->boxfield[0]->processing);
		  $oneline = formatReceipt($value, $field->width, $field->align, $oneline);
		  break;
		case 'TBlk':
		  if (!$field->boxfield[0]->fieldname) throw new \core\classes\userException(PHREEFORM_EMPTYFIELD . $field->fieldname);
		  if ($report->special_class) {
			$TextField = $special_form->load_text_block_data($field->boxfield);
		  } else {
			$arrTxtBlk = array(); // Build the fieldlist
			foreach ($field->boxfield as $idx => $entry) $arrTxtBlk[] = prefixTables($entry->fieldname) . ' as r' . $idx;
			$strTxtBlk    = implode(', ', $arrTxtBlk);
			$result       = $admin->DataBase->query("select " . $strTxtBlk . $TrailingSQL);
			$TextField    = '';
			for ($i = 0; $i < sizeof($field->boxfield); $i++) {
		      $temp = $field->boxfield[$i]->formatting ? ProcessData($result->fields['r'.$i], $field->boxfield[$i]->formatting) : $result->fields['r'.$i];
			  $TextField .= AddSep($temp, $field->boxfield[$i]->processing);
			}
		  }
		  $report->fieldlist[$key]->text = $TextField;
		  $oneline = $report->fieldlist[$key]->text;
		  break;
		case 'Tbl':
		  	if (!$field->boxfield) throw new \core\classes\userException(PHREEFORM_EMPTYFIELD . $field->description);
//		  	$tblHeading = array();
//		  	foreach ($field->boxfield as $TableField) $tblHeading[] = $TableField->description;
		  	$data = array();
		  	if ($report->special_class) {
				$data = $special_form->load_table_data($field->boxfield);
		  	} else {
			  	$tblField = array();
			  	foreach ($field->boxfield as $key => $TableField) $tblField[] = prefixTables($TableField->fieldname) . ' as r' . $key;
			  	$tblField = implode(', ', $tblField);
			  	$result = $admin->DataBase->query("select " . $tblField . $TrailingSQL);
			  	while (!$result->EOF) {
					$data[] = $result->fields;
					$result->MoveNext();
			  	}
		  	}
		  	$field->data = $data;
		  	$StoredTable = $field;
		  	foreach ($data as $key => $value) {
				$temp = array();
				foreach ($value as $data_key => $data_element) {
			  		$offset  = substr($data_key, 1);
			  		$value   = ProcessData($data_element, $field->boxfield[$offset]->processing);
			  		$temp[] .= formatReceipt($value, $field->boxfield[$offset]->width, $field->boxfield[$offset]->align);
				}
				$oneline .= implode("", $temp). "\n";
		  	}
		  	$oneline = substr($oneline, 0, -2);//strip the last \n
		  	$field->rowbreak = 1;
		  	break;
		case 'TDup':
		  if (!$StoredTable) throw new \core\classes\userException(PHREEFORM_EMPTYTABLE . $field->description);
		  // insert new coordinates into existing table
		  $StoredTable->abscissa = $field->abscissa;
		  $StoredTable->ordinate = $field->ordinate;
		  foreach ($StoredTable->data as $key => $value) {
			$temp = array();
			foreach ($value as $data_key => $data_element) {
			  $value   = ProcessData($data_element, $report->boxfield[$data_key]->processing);
			  $temp[] .= formatReceipt($value, $field->width, $field->align);
			}
			$oneline = implode("", $temp);
		  }
		  $field->rowbreak = 1;
		  break;
		case 'Ttl':
		  if (!$field->boxfield) throw new \core\classes\userException(PHREEFORM_EMPTYFIELD . $field->description);
		  if ($report->special_class) {
			$FieldValues = $special_form->load_total_results($field);
		  } else {
			$ttlField = '';
			foreach ($field->boxfield as $TotalField) $ttlField[] = prefixTables($TotalField->fieldname);
			$sql         = "select sum(" . implode(' + ', $ttlField) . ") as form_total" . $TrailingSQL;
			$result      = $admin->DataBase->query($sql);
			$FieldValues = $result->fields['form_total'];
		  }
		  $value   = ProcessData($FieldValues, $report->boxfield[0]->processing);
		  $oneline = formatReceipt($value, $field->width, $field->align, $oneline);
		  break;
	  }
	  if ($field->rowbreak) {
	    $output .= $oneline . "\n";
		$oneline = '';
	  }
    }
    // set the printed flag field if provided
    if ($report->setprintedflag) {
	  $id_field = $report->formbreakfield;
	  $temp     = explode('.', $report->setprintedflag);
	  if (sizeof($temp) == 2) { // need the table name and field name
	    $sql = "update " . $temp[0] . " set " . $temp[1] . " = " . $temp[1] . " + 1 where " . $report->formbreakfield . " = '" . $Fvalue . "'";
	    $admin->DataBase->query($sql);
	  }
    }
    $output .= "\n\n\n\n"; // page break
  }
  if ($delivery_method == 'S') return $output;
  $FileSize = strlen($output);
  header_remove();
  header("Content-type: application/text");
  header("Content-disposition: attachment; filename=" . $report->filenameprefix . ".txt; size=" . $FileSize);
  header('Pragma: cache');
  header('Cache-Control: public, must-revalidate, max-age=0');
  header('Connection: close');
  header('Expires: ' . date('r', time()+60*60));
  header('Last-Modified: ' . date('r', time()));
  print $output;
  exit();
}

function formatReceipt($value, $width = '15', $align = 'z', $base_string = '', $keep_nl = false) {
  $temp   = explode(chr(10), $value);
  $output = NULL;
  foreach ($temp as $key => $value) {
	if ($key > 0) $output .= "\n"; // keep the new line chars
    switch ($align) {
      case 'L':
	    if (strlen($base_string)) $output .= $value . substr($base_string, $width - strlen($value));
		  else $output .= str_pad($value, $width, ' ', STR_PAD_RIGHT);
		break;
      case 'R':
	    if (strlen($base_string)) $output .= substr($base_string, 0, $width - strlen($value)) . $value;
	      else $output .= str_pad($value, $width, ' ', STR_PAD_LEFT);
		break;
      case 'C':
	    if (strlen($base_string)) {
		  $pad = (($width - strlen($value)) / 2);
	      $output .= substr($base_string, 0, floor($pad)) . $value . substr($base_string, -ceil($pad));
		} else {
		  $num_blanks = (($width - strlen($value)) / 2) + strlen($value);
	      $value   = str_pad($value, intval($num_blanks), ' ', STR_PAD_LEFT);
	      $output .= str_pad($value, $width,              ' ', STR_PAD_RIGHT);
	    }
		break;
    }
//echo 'value = ' . $value . ' and base_string = ' . $base_string . ' and new string = ' . $output . "<br>\n";
  }
  return $output;
}

function prefixTables($field) {
  global $report;
  foreach ($report->tables as $table) {
    $field = str_replace($table->tablename . '.', DB_PREFIX . $table->tablename . '.', $field);
  }
  return $field;
}

function testTables($field, $report) { // tests for the existence of tables in variables (for formulas)
  $result = false;
  foreach ($report->tables as $table) {
    if (strpos($field, $table->tablename . '.') !== false) $result = true;
  }
  return $result;
}

function build_criteria($report) {
  global $CritChoices;
  $strCrit    = '';
  $filCrit    = '';
  $crit_prefs = $report->filterlist;
  if (!is_array($crit_prefs))         $crit_prefs   = array();
  if (is_array($report->xfilterlist)) $crit_prefs[] = $report->xfilterlist[0];
  while ($FieldValues = array_shift($crit_prefs)) {
	if (!$FieldValues->default) { // if no selection was passed, assume it's the first on the list for that selection menu
	  $temp = explode(':', $CritChoices[$FieldValues->type]);
	  $FieldValues->default = $temp[1];
	}
	$sc = '';
	$fc = '';
	switch ($FieldValues->default) {
	  case 'RANGE':
		if ($FieldValues->min_val) { // a from value entered, check
		  $sc .= prefixTables($FieldValues->fieldname) . " >= '" . $FieldValues->min_val . "'";
		  $fc .= $FieldValues->description . " >= " . $FieldValues->min_val;
		}
		if ($FieldValues->max_val) { // a to value entered, check
		  if (strlen($sc)>0) { $sc .= ' AND '; $fc .= ' ' . TEXT_AND . ' '; }
		  $sc .= prefixTables($FieldValues->fieldname) . " <= '" . $FieldValues->max_val . "'";
		  $fc .= $FieldValues->description . " <= " . $FieldValues->max_val;
		}
		break;
	  case 'YES':
	  case 'TRUE':
	  case 'INACTIVE':
	  case 'PRINTED':
		$sc .= prefixTables($FieldValues->fieldname) . ' = \'1\'';
		$fc .= $FieldValues->description . ' = ' . $FieldValues->default;
		break;
	  case 'NO':
	  case 'FALSE':
	  case 'ACTIVE':
	  case 'UNPRINTED':
		$sc .= prefixTables($FieldValues->fieldname) . ' = \'0\'';
		$fc .= $FieldValues->description . ' = ' . $FieldValues->default;
		break;
	  case 'EQUAL':
	  case 'NOT_EQUAL':
	  case 'GREATER_THAN':
	  case 'LESS_THAN':
		if ($FieldValues->default == 'EQUAL')        $sign = " = ";
		if ($FieldValues->default == 'NOT_EQUAL')    $sign = " <> ";
		if ($FieldValues->default == 'GREATER_THAN') $sign = " > ";
		if ($FieldValues->default == 'LESS_THAN')    $sign = " < ";
		if (isset($FieldValues->min_val)) { // a from value entered, check
		  $q_field = testTables($FieldValues->min_val, $report) ? prefixTables($FieldValues->min_val) : "'" . prefixTables($FieldValues->min_val) . "'";
		  $sc .= prefixTables($FieldValues->fieldname) . $sign . $q_field;
		  $fc .= $FieldValues->description . $sign . $FieldValues->min_val;
		}
		break;
	  case 'IN_LIST':
		if (isset($FieldValues->min_val)) { // a from value entered, check
		  $csv_values = explode(',', $FieldValues->min_val);
		  for ($i = 0; $i < sizeof($csv_values); $i++) $csv_values[$i] = trim($csv_values[$i]);
		  $sc .= prefixTables($FieldValues->fieldname) . " in ('" . implode("','", $csv_values) . "')";
		  $fc .= $FieldValues->description . " in (" . $FieldValues->min_val . ")";
		}
		break;
	  case 'ALL': // sql default anyway
	default:
	}
	if ($sc) {
	  if (strlen($strCrit) > 0) {
		$strCrit .= ' and ';
		if ($FieldValues->visible) $filCrit .= ' ' . TEXT_AND . ' ';
	  }
	  $strCrit .= $sc;
	  if ($FieldValues->visible) $filCrit .= $fc;
	}
  }
  $criteria = array('sql' => $strCrit, 'description' => $filCrit);
  return $criteria;
}

function BuildSQL($report) { // for reports only
	$strField = array();
	$index = 0;
	for ($i = 0; $i < sizeof($report->fieldlist); $i++) {
	  if ($report->fieldlist[$i]->visible) {
		$strField[] = prefixTables($report->fieldlist[$i]->fieldname) . " AS c" . $index;
		$index++;
	  }
	}
	if (!$strField) return array('level' => 'error', 'message' => PHREEFORM_NOROWS);
	$strField = implode(', ', $strField);

	$filterdesc = TEXT_REPORT_FILTERS. ': '; // Initialize the filter display string
	//fetch the groupings and build first level of SORT BY string (for sub totals)
	$strGroup = NULL;
	for ($i = 0; $i < sizeof($report->grouplist); $i++) {
	  if ($report->grouplist[$i]->default) {
		$strGroup   .= prefixTables($report->grouplist[$i]->fieldname);
		$filterdesc .= TEXT_GROUPED_BY . ': ' . $report->grouplist[$i]->description . '; ';
		break;
	  }
	}
	// fetch the sort order and add to group by string to finish ORDER BY string
	$strSort = $strGroup;
	for ($i = 0; $i < sizeof($report->sortlist); $i++) {
	  if ($report->sortlist[$i]->default) {
		$strSort    .= ($strSort <> '' ? ', ' : '') . prefixTables($report->sortlist[$i]->fieldname);
		$filterdesc .= TEXT_SORTED_BY . ':  ' . $report->sortlist[$i]->description . '; ';
		break;
	  }
	}
	// fetch date filter info
	$tempDate = new \core\classes\DateTime();
	$dates   = $tempDate->sql_date_array($report->datedefault, prefixTables($report->datefield));
	$strDate = $dates['sql'];
	if ($dates['description']) $filterdesc .= $dates['description']; // update the filter description string
	// Fetch the Criteria
	$criteria = build_criteria($report);
	$strCrit  = $criteria['sql'];
	if ($criteria['description']) $filterdesc .= TEXT_FILTERS. " : " . ' ' . $criteria['description'] . '; ';
	// fetch the tables to query
	$sqlTable = '';
	foreach ($report->tables as $table) {
	  if (isset($table->relationship)) {
	    if (!$table->joinopt) $table->joinopt = 'JOIN';
	    $sqlTable .= ' ' . $table->joinopt . ' ' . DB_PREFIX . $table->tablename . ' ON ' . prefixTables($table->relationship);
	  } else {
	    $sqlTable .= DB_PREFIX . $table->tablename;
	  }
	}
	// Build query string
	$sql = 'SELECT ' . $strField . ' FROM ' . $sqlTable;
	if ( $strCrit &&  $strDate) $sql .= ' WHERE '    . $strDate . ' AND ' . $strCrit;
	if (!$strCrit &&  $strDate) $sql .= ' WHERE '    . $strDate;
	if ( $strCrit && !$strDate) $sql .= ' WHERE '    . $strCrit;
	if ( $strSort)              $sql .= ' ORDER BY ' . $strSort;
//echo 'sql = '; print_r($sql); echo '<br><br>'; exit();
//echo 'period = '; print_r($report->period); echo '<br><br>';
	return array(
	  'level'       => 'success',
	  'data'        => $sql,
	  'description' => $filterdesc,
	);
}

function BuildDataArray($sql, $report) { // for reports only
	global $admin, $Heading, $Seq, $posted_currencies, $messageStack;
	$posted_currencies = array('currencies_code' => DEFAULT_CURRENCY, 'currencies_value' => 1); // use default currency
	// See if we need to group, fetch the group fieldname
	$GrpFieldName = '';
	if (is_array($report->grouplist)) while ($Temp = array_shift($report->grouplist)) {
		if ($Temp->default) {
			$GrpFieldName       = $Temp->fieldname;
			$GrpFieldProcessing = $Temp->processing;
			break;
		}
	}
	// Build the sequence map of retrieved fields, order is as user wants it
	$i = 0;
	$GrpField = '';
	foreach ($report->fieldlist as $DataFields) {
	  if ($DataFields->visible) {
		if ($DataFields->fieldname == $GrpFieldName) $GrpField = 'c' . $i;
		$Heading[]             = $DataFields->description;
		$Seq[$i]['break']      = $DataFields->columnbreak;
		$Seq[$i]['fieldname']  = 'c' . $i;
		$Seq[$i]['total']      = $DataFields->total;
		$Seq[$i]['processing'] = $DataFields->processing;
		$Seq[$i]['align']      = $DataFields->align;
		$Seq[$i]['grptotal']   = '';
		$Seq[$i]['rpttotal']   = '';
		$i++;
	  }
	}
	// patch for special_reports where the data file is generated externally from the standard function
	if ($report->special_class) {
      	$path = find_special_class($report->special_class);
	  	load_special_language($path, $report->special_class);
	  	require_once($path . '/classes/' . $report->special_class . '.php');
	  	$sp_report = new $report->special_class; //@todo needs new location
		return $sp_report->load_report_data($report, $Seq, $sql, $GrpField); // the special report formats all of the data, we're done
	}

	$result = $admin->DataBase->query($sql);
	if ($result->fetch(\PDO::FETCH_NUM) == 0) throw new \core\classes\userException("no data"); // No data so bail now
	// Generate the output data array
	$RowCnt     = 0;
	$ColCnt     = 1;
	$GrpWorking = false;
	while (!$result->EOF) {
	  $myrow = $result->fields;
	  // Check to see if a total row needs to be displayed
	  if (isset($GrpField)) { // we're checking for group totals, see if this group is complete
		if (($myrow[$GrpField] <> $GrpWorking) && $GrpWorking !== false) { // it's a new group so print totals
		  $OutputArray[$RowCnt][0] = 'g:' . ProcessData($GrpWorking, $GrpFieldProcessing);
		  foreach($Seq as $offset => $TotalCtl) {
			$OutputArray[$RowCnt][$offset+1] = ($TotalCtl['total'] == '1') ? ProcessData($TotalCtl['grptotal'], $TotalCtl['processing']) : ' ';
			$Seq[$offset]['grptotal'] = ''; // reset the total
		  }
		  $RowCnt++; // go to next row
		}
		$GrpWorking = $myrow[$GrpField]; // set to new grouping value
	  }

	  foreach($Seq as $key => $TableCtl) { //
	    if ($report->totalonly <> '1') { // insert data into output array and set to next column
		  $OutputArray[$RowCnt][0] = 'd'; // let the display class know its a data element
	      $OutputArray[$RowCnt][$ColCnt] = ProcessData($myrow[$TableCtl['fieldname']], $TableCtl['processing']);
	    }
	    $ColCnt++;
	    if ($TableCtl['total']) { // add to the running total if need be
		  $Seq[$key]['grptotal'] += $admin->currencies->clean_value(ProcessData($myrow[$TableCtl['fieldname']], $TableCtl['processing']));
		  $Seq[$key]['rpttotal'] += $admin->currencies->clean_value(ProcessData($myrow[$TableCtl['fieldname']], $TableCtl['processing']));
	    }
	  }
	  $RowCnt++;
	  $ColCnt = 1;
	  $result->MoveNext();
	}
	if ($GrpWorking !== false) { // if we collected group data show the final group total
		$OutputArray[$RowCnt][0] = 'g:' . ProcessData($GrpWorking, $GrpFieldProcessing);
		foreach ($Seq as $TotalCtl) {
			$OutputArray[$RowCnt][$ColCnt] = ($TotalCtl['total'] == '1') ? $TotalCtl['grptotal'] : ' ';
			$ColCnt++;
		}
		$RowCnt++;
		$ColCnt = 1;
	}
	// see if we have a total to send
	$ShowTotals = false;
	foreach ($Seq as $TotalCtl) if ($TotalCtl['total']=='1') $ShowTotals = true;
	if ($ShowTotals) {
		$OutputArray[$RowCnt][0] = 'r:' . $report->title;
		foreach ($Seq as $TotalCtl) {
			if ($TotalCtl['total']) $OutputArray[$RowCnt][$ColCnt] = $TotalCtl['rpttotal'];
				else $OutputArray[$RowCnt][$ColCnt] = ' ';
			$ColCnt++;
		}
	}
//echo 'output array = '; print_r($OutputArray); echo '<br />'; exit();
	return $OutputArray;
}

function ReplaceNonAllowedCharacters($input) {
	return str_replace(array('"', "'", ' ', '&', "/", "\\"), "_", $input);
}

function GeneratePDFFile($Data, $report, $delivery_method = 'D') { // for pdf reports only
	$pdf = new \phreeform\classes\report_generator();
	$pdf->ReportTable($Data);
	$ReportName = ReplaceNonAllowedCharacters($report->title) . '.pdf';
	if ($delivery_method == 'S') return array('filename' => $ReportName, 'pdf' => $pdf->Output($ReportName, 'S'));
	header_remove();
	header('Content-type: application/pdf');
	header('Content-Disposition: inline; filename=' . $ReportName);
	header('Expires: 0');
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');
	$pdf->Output($ReportName, $delivery_method);
	exit(); // needs to be here to properly render the pdf file.
}

function GenerateHTMLFile($Data, $report, $delivery_method = 'D') { // for html reports only
	$html        = new \phreeform\classes\html_generator();
	$html->title = ReplaceNonAllowedCharacters($report->title);
	$html->ReportTable($Data);
	$ReportName = ReplaceNonAllowedCharacters($report->title) . '.html';
	if ($delivery_method == 'S') return array('filename' => $ReportName, 'pdf' => $html->output);
	echo $html->output;
	exit();
}

function GenerateCSVFile($Data, $report, $delivery_method = 'D') { // for csv reports only
	global $Heading, $posted_currencies;
	$posted_currencies = array('currencies_code' => DEFAULT_CURRENCY, 'currencies_value' => 1); // use default currency
	$CSVOutput = '';
	// Write the column headings
	foreach ($Heading as $mycolumn) { // check for embedded commas and enclose in quotes
	  $CSVOutput .= (strpos($mycolumn, ',') === false) ? ($mycolumn . ',') : ('"' . $mycolumn . '",');
	}
	$CSVOutput = substr($CSVOutput, 0, -1) . chr(10); // Strip the last comma off and add line feed
	// Now write each data line and totals
	foreach ($Data as $myrow) {
	  $Action = array_shift($myrow);
	  $todo = explode(':', $Action); // contains a letter of the date type and title/groupname
	  switch ($todo[0]) {
		case "r": // Report Total
		case "g": // Group Total
		  $Desc = ($todo[0] == 'g') ? TEXT_GROUP_TOTAL_FOR . ': ' : TEXT_REPORT_TOTAL_FOR;
		  $CSVOutput .= $Desc . $todo[1] . chr(10);
		  // Now write the total data like any other data row
		case "d": // Data
		default:
		  $CSVLine = '';
		  foreach ($myrow as $mycolumn) { // check for embedded commas and enclose in quotes
			$CSVLine .= (strpos($mycolumn, ',') === false) ? ($mycolumn . ',') : ('"' . $mycolumn . '",');
		  }
		  $CSVLine = substr($CSVLine, 0, -1); // Strip the last comma off
	  }
	  $CSVOutput .= $CSVLine . chr(10);
	}
	$ReportName = ReplaceNonAllowedCharacters($report->title) . '.csv';
	if ($delivery_method == 'S') return array('filename' => $ReportName, 'pdf' => $CSVOutput);
	header_remove();
	header("Content-type: application/csv");
	header("Content-disposition: attachment; filename=" . $report->title . ".csv; size=" . strlen($CSVOutput));
	header('Pragma: cache');
	header('Cache-Control: public, must-revalidate, max-age=0');
	header('Connection: close');
	header('Expires: ' . date('r', time()+60*60));
	header('Last-Modified: ' . date('r'));
	print $CSVOutput;
	exit();
}

function GenerateXMLFile($Data, $report, $delivery_method = 'D') { // for csv reports only
	global $Heading, $posted_currencies;
	// Now write each data line and totals
	print_r($Data);
	foreach ($Data as $myrow) {
		$xml .= '<Row>'.chr(10);
	  	$Action = array_shift($myrow);
	  	$todo = explode(':', $Action); // contains a letter of the date type and title/groupname
	  	switch ($todo[0]) {
			case "r": // Report Total
			case "g": // Group Total
		  		$Desc = ($todo[0] == 'g') ? TEXT_GROUP_TOTAL_FOR . ': ' : TEXT_REPORT_TOTAL_FOR;
		  		$xml .= '<' . $Desc .'>' . $todo[1] . '</' . $Desc .'>'. chr(10);
			  // Now write the total data like any other data row
			case "d": // Data
			default:
		  		$i = 0;
		  		foreach ($Heading as $title){
		  		//foreach ($myrow as $mycolumn) { // check for embedded commas and enclose in quotes
		  			$xml .= '<'.$title.'>'.$myrow[$i].'</'.$title.'>'.chr(10);
					$i++;
		  		}
		  }
	  $xml .= '</Row>'.chr(10);

	}
	$ReportName = ReplaceNonAllowedCharacters($report->title) . '.csv';
	if ($delivery_method == 'S') return array('filename' => $ReportName, 'pdf' => $CSVOutput);
	$output  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . chr(10);
	$output .= '<PhreeformReport>' . chr(10);
	$output .= $xml;
	$output .= '</PhreeformReport>' . chr(10);

	print $output;
	ob_end_flush();
	session_write_close();
	exit();
}

function CreateFieldArray($report) {
  global $admin;
  $output = array(array('id' => '', 'text' => TEXT_SELECT));
  if (is_array($report->tables)) foreach ($report->tables as $tObj) {
    if ($tObj->tablename) {
	  $result = $admin->DataBase->query("describe " . DB_PREFIX . $tObj->tablename);
	  while (!$result->EOF) {
	    $fieldname = $tObj->tablename . '.' . $result->fields['Field'];
	    $output[] = array('id' => $fieldname, 'text' => DB_PREFIX . $fieldname);
	    $result->MoveNext();
	  }
	}
  }
  return $output;
}

function strip_tablename($value) {
  if (strpos($value, '.') !== false) {
    return substr($value, strpos($value, '.') + 1);
  } else {
    return $value;
  }
}

function CreateTableList($report) {
  global $admin;
  $output = array(array('id' => '', 'text' => TEXT_SELECT));
  $result = $admin->DataBase->query("show tables");
  while (!$result->EOF) {
    $tablename = array_shift($result->fields);
    $BaseTableName = (DB_PREFIX) ? substr($tablename, strlen(DB_PREFIX)) : $tablename;
    $output[] = array('id' => $BaseTableName, 'text' => $tablename);
    $result->MoveNext();
  }
  return $output;
}

function CreateSpecialDropDown($report) {
	if ($report->special_class) {
	    $path = find_special_class($report->special_class);
	    load_special_language($path, $report->special_class);
	    require_once($path . "/classes/{$report->special_class}.php");
	    $sp_report = new $report->special_class;
	    return $sp_report->build_selection_dropdown();
	}
	return CreateFieldArray($report);
}

function CreateFieldTblDropDown($report) {
	if ($report->special_class) {
	    $path = find_special_class($report->special_class);
	    load_special_language($path, $report->special_class);
	    require_once($path . "/classes/{$report->special_class}.php");
	    $sp_report = new $report->special_class;
	    return $sp_report->build_table_drop_down();
	}
	return CreateFieldArray($report);
}

function crit_build_pull_down($keyed_array) {
  $values = array();
  if (is_array($keyed_array)) {
    foreach ($keyed_array as $key => $value) {
	  $value = substr($value, 2);
	  $words = explode(':', $value);
	  foreach ($words as $idx => $word) $words[$idx] = constant('TEXT_' . $word);
	  $values[] = array('id' => $key, 'text' => implode(':', $words));
    }
  }
  return $values;
}

function ReadImages() {
  $OptionList = array();
  $dh = opendir(PF_DIR_MY_REPORTS . 'images/');
  while ($DefRpt = readdir($dh)) {
	$pinfo = pathinfo(PF_DIR_MY_REPORTS . 'images/' . $DefRpt);
	$Ext = strtoupper($pinfo['extension']);
	if ($Ext == 'JPG' || $Ext == 'JPEG' || $Ext == 'PNG') { //fpdf only supports JPG and PNG formats!!!
	  $OptionList[] = array('id' => $pinfo['basename'], 'text' => $pinfo['basename']) ;
	}
  }
  closedir($dh);
  return $OptionList;
}

function CreateCompanyArray() {
  $company_array = array(
	array('id' => '',                    'text' => sprintf(TEXT_SELECT_ARGS, TEXT_FIELD)),
	array('id' => 'COMPANY_NAME',        'text' => COMPANY_NAME),
	array('id' => 'COMPANY_ADDRESS1',    'text' => COMPANY_ADDRESS1),
	array('id' => 'COMPANY_ADDRESS2',    'text' => COMPANY_ADDRESS2),
	array('id' => 'COMPANY_CITY_TOWN',   'text' => COMPANY_CITY_TOWN),
	array('id' => 'COMPANY_ZONE',        'text' => COMPANY_ZONE),
	array('id' => 'COMPANY_POSTAL_CODE', 'text' => COMPANY_POSTAL_CODE),
	array('id' => 'COMPANY_COUNTRY',     'text' => COMPANY_COUNTRY),
	array('id' => 'COMPANY_TELEPHONE1',  'text' => COMPANY_TELEPHONE1),
	array('id' => 'COMPANY_TELEPHONE2',  'text' => COMPANY_TELEPHONE2),
	array('id' => 'COMPANY_FAX',         'text' => COMPANY_FAX),
	array('id' => 'COMPANY_EMAIL',       'text' => COMPANY_EMAIL),
	array('id' => 'COMPANY_WEBSITE',     'text' => COMPANY_WEBSITE),
	array('id' => 'TAX_ID',              'text' => TAX_ID),
	array('id' => 'COMPANY_ID',          'text' => COMPANY_ID),
	array('id' => 'AR_CONTACT_NAME',     'text' => AR_CONTACT_NAME),
	array('id' => 'AP_CONTACT_NAME',     'text' => AP_CONTACT_NAME),
  );
  return $company_array;
}

?>