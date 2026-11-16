<?php
	session_start ();

	// Credentials (../config/db_config.php or SKYLINE_DB_* env vars) and the query helpers; see db_connect.php.
	require_once __DIR__ . '/db_connect.php';

	// The page builds this URL without encoding, so the raw REQUEST_URI is parsed on purpose: a '+' in a
	// population name must stay a '+' (PHP's $_GET would turn it into a space). Values keep their %20s.
	$url = $_SERVER['REQUEST_URI'] ?? '';
	$tmp = explode("=", $url);
	//print_r($tmp);
	$tmp1 = isset($tmp[1]) ? explode("&", $tmp[1]) : array('');
	$type = $tmp1[0] ?? '';
	$tmp2 = isset($tmp[2]) ? explode("&", $tmp[2]) : array('');
	$probeset_id = $tmp2[0] ?? '';
	$tmp3 = isset($tmp[3]) ? explode("&", $tmp[3]) : array('');
	$datagroup = $tmp3[0] ?? '';
	$tmp4 = isset($tmp[4]) ? explode("&", $tmp[4]) : array('');
	$population = $tmp4[0] ?? '';
	$pop_info=explode(",", $population);
	//print_r($pop_info);
	$key_population = $tmp[5] ?? '';


	$key_pop_info = array('LTHSC_34-_BM','MMP2_150+48+_BM','proB_CLP_BM','proB_FrBC_BM','B_Fo_Sp','B_MZ_Sp','B_GC_CC_Sp','B_PC_Sp','B1b_PC','preT_DN1_Th','T_DP_Th','T_4_Nve_Sp','NKT_Sp','Treg_4_25hi_Sp','T_8_Nve_Sp','T8_TE_LCMV_d7_Sp','T8_Tem_LCMV_d180_Sp','Tgd_g2+d1_24a+_Th','Tgd_g2+d17_LN','Tgd_Sp','NK_27-11b+_Sp','ILC2_SI','ILC3_NKp46-CCR6-_SI','ILC3_NKp46+_SI','DC_8+_Sp','DC_pDC_Sp','GN_BM','GN_Thio_PC','MC_heparinase_PC','MF_PC','MF_Alv_Lu','Ep_MEChi_Th','LEC_SLN','IAP_SLN','MAIT_Sp');
	$key_pop_info_OSMNP = array('MF_64pLYVEpIIp_Ao','EMP_E10_5_YS','DC_24p8anXCR1n_Sp','DC_XCR1nSIRPap_Th','MF_115pICAM2p226n6Cn_PC','Mo_6Cp_Bl','MF_E16_5_PC','MF_KC_Clec4FpTim4p64p_Lv','MF_11cpSigFp_BAL','Mo_6Cp_Lu','MF_CCR2p64p6ClonGFPp_Ht','MF_45lo_CNS','MF_microglia_CNS','DC_8ap_PP','MF_DRG','MF_B220n6Cn64pTim4p_PC');
	$immgenT_excluded_pops = array('nonT','thymocyte','unclear.cl14','unclear.cl25','unclear.cl37','unclear.cl47','unclear.miniverse','unclear.thymocyte');

	// Whitelist: datagroup (as it appears in the URL) => data-group table, data_group value, output columns,
	// and the population filter for "Reference populations": array(operator, populations), or null for all.
	$datagroups = array(
		'ImmGen%20ULI%20RNASeq' => array('ImmGen_data_group_20230510', 'ImmGen ULI RNASeq', 'short_name, long_name, description', array('IN', $key_pop_info)),
		'ImmGen%20MNP%20OpenSource' => array('ImmGen_data_group_20230510', 'ImmGen MNP OpenSource', 'short_name, long_name, description', array('IN', $key_pop_info_OSMNP)),
		'ImmGen%20CGC' => array('ImmGen_data_group_CGC_20210430', 'ImmGen CGC', 'short_name, long_name, description', array('NOT IN', array('MFrp.IL15'))),
		'ImmGen%20Cytokines%20Inflammatory' => array('ImmGen_data_group_Cytokines_Inflammatory_20230727', 'ImmGen Cytokines Inflammatory', 'short_name, long_name, description', null),
		'immgenT%20Pseudobulk' => array('ImmGen_data_group_immgenT_Pseudobulk', 'immgenT Pseudobulk', 'population_group, population', array('NOT IN', $immgenT_excluded_pops)),
	);
	if ($type != 'rnaseq') { skyline_fail(400, 'Invalid type'); }
	if (!array_key_exists($datagroup, $datagroups)) { skyline_fail(400, 'Invalid datagroup'); }
	list($group_table, $group_name, $out_columns, $reference_filter) = $datagroups[$datagroup];

	if ($key_population == 'Reference%20populations') {
		$filter = $reference_filter;
	}
	else {
		$selected = ($datagroup == 'immgenT%20Pseudobulk') ? array_diff($pop_info, $immgenT_excluded_pops) : $pop_info;
		$filter = array('IN', $selected);
	}
	// Only server-side names and the operator from the table above are concatenated; populations are bound.
	$params = array($group_name);
	$pop_where = '';
	if ($filter !== null) {
		list($sql_list, $values) = skyline_in_list($filter[1]);
		$pop_where = ' AND population ' . $filter[0] . ' ' . $sql_list;
		$params = array_merge($params, $values);
	}
	$rnaseq_meta_query = "SELECT ".$out_columns." FROM ".skyline_ident($group_table)." WHERE data_group = ?".$pop_where." ORDER BY `order`";

	$con = skyline_db_connect();
	$meta_result = skyline_query($con, $rnaseq_meta_query, $params);


	$filename = "meta_table.csv";
	$delimiter = ",";

	// output headers so that the file is downloaded rather than displayed
	 //ob_clean();
	 header('Content-Type: application/csv');
	 header('Content-Disposition: attachment;filename='.$filename);

	// create a file pointer connected to the output stream
	$output = fopen('php://output', 'w');

	if($datagroup == 'immgenT%20Pseudobulk'){
		$outnames = array('Population Group', 'Population');
	}
	else{
		$outnames = array('Population Name', 'Population Long Name', 'Description');
	}

	// output the column headings
	fputcsv($output, $outnames);
	//echo $filename;

	// loop over the rows, outputting them
	while ($row = skyline_fetch_text_row($meta_result, MYSQLI_NUM)) {
		fputcsv($output, $row);
	}
	exit;


?>
