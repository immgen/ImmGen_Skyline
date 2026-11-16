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


	$immgen_data_group = 'ImmGen_data_group_20230510';
	$rnaseq_meta_table = 'ImmGen_RNAseq_meta_info_20200610';
	$key_pop_info = array('LTHSC_34-_BM','MMP2_150+48+_BM','proB_CLP_BM','proB_FrBC_BM','B_Fo_Sp','B_MZ_Sp','B_GC_CC_Sp','B_PC_Sp','B1b_PC','preT_DN1_Th','T_DP_Th','T_4_Nve_Sp','NKT_Sp','Treg_4_25hi_Sp','T_8_Nve_Sp','T8_TE_LCMV_d7_Sp','T8_Tem_LCMV_d180_Sp','Tgd_g2+d1_24a+_Th','Tgd_g2+d17_LN','Tgd_Sp','NK_27-11b+_Sp','ILC2_SI','ILC3_NKp46-CCR6-_SI','ILC3_NKp46+_SI','DC_8+_Sp','DC_pDC_Sp','GN_BM','GN_Thio_PC','MC_heparinase_PC','MF_PC','MF_Alv_Lu','Ep_MEChi_Th','LEC_SLN','IAP_SLN','MAIT_Sp');
	$key_pop_info_OSMNP = array('MF_64pLYVEpIIp_Ao','MF_E10_5_YS','DC_24p8anXCR1n_Sp','DC_XCR1nSIRPap_Th','MF_115pICAM2p226n6Cn_PC','Mo_6Cp_Bl','MF_E16_5_PC','MF_KC_Clec4FpTim4p64p_Lv','MF_11cpSigFp_BAL','Mo_6Cp_Lu','MF_CCR2p64p6ClonGFPp_Ht','MF_45lo_CNS','MF_microglia_CNS','DC_8ap_PP','MF_DRG','MF_B220n6Cn64pTim4p_PC');
	$key_pop_info_CGC = array("MC.IL2","MC.IL4","MC.IL7","MC.IL9","MC.IL15","MC.IL21","MC.PBS","MFpc.IL2","MFpc.IL4","MFpc.IL7","MFpc.IL9","MFpc.IL15","MFpc.IL21","MFpc.PBS","MFrp.IL4","MFrp.IL7","MFrp.IL21","MFrp.PBS","Mo.IL2","Mo.IL4","Mo.IL7","Mo.IL9","Mo.IL15","Mo.IL21","Mo.PBS","DC8.IL2","DC8.IL4","DC8.IL7","DC8.IL9","DC8.IL15","DC8.IL21","DC8.PBS","pDC.IL2","pDC.IL4","pDC.IL7","pDC.IL9","pDC.IL15","pDC.IL21","pDC.PBS","Bfo.IL2","Bfo.IL4","Bfo.IL7","Bfo.IL9","Bfo.IL15","Bfo.IL21","Bfo.PBS","MZB.IL2","MZB.IL4","MZB.IL7","MZB.IL9","MZB.IL15","MZB.IL21","MZB.PBS","NK.IL2","NK.IL4","NK.IL7","NK.IL15","NK.IL21","NK.PBS","Tgd.IL2","Tgd.IL4","Tgd.IL7","Tgd.IL9","Tgd.IL15","Tgd.IL21","Tgd.PBS","T8em.IL2","T8em.IL4","T8em.IL7","T8em.IL9","T8em.IL15","T8em.IL21","T8em.PBS","T4n.IL2","T4n.IL4","T4n.IL7","T4n.IL9","T4n.IL15","T4n.IL21","T4n.PBS","Treg.IL2","Treg.IL4","Treg.IL7","Treg.IL9","Treg.IL15","Treg.IL21","Treg.PBS");
	$key_pop_info_IC = array("MC.PBS","MC.IL1b","MC.IL6","MC.TNFa","GN.PBS","GN.IL1b","GN.IL6","GN.TNFa","MFpc.PBS","MFpc.IL1b","MFpc.IL6","MFpc.TNFa","MFrp.PBS","MFrp.IL1b","MFrp.IL6","MFrp.TNFa","Mo.PBS","Mo.IL1b","Mo.IL6","Mo.TNFa","DC.8.PBS","DC.8.IL1b","DC.8.IL6","DC.8.TNFa","pDC.PBS","pDC.IL6","pDC.TNFa","B.fo.PBS","B.fo.IL1b","B.fo.IL6","B.fo.TNFa","MZB.PBS","MZB.IL1b","MZB.IL6","MZB.TNFa","NK.PBS","NK.IL1b","NK.IL6","NK.TNFa","Tgd.PBS","Tgd.IL1b","Tgd.IL6","Tgd.TNFa","T.8.PBS","T.8.IL1b","T.8.IL6","T.8.TNFa","T.4.PBS","T.4.IL1b","T.4.IL6","T.4.TNFa","Treg.PBS","Treg.IL1b","Treg.IL6","Treg.TNFa");
	$key_pop_info_iTP_full = array("CD4.A","CD4.B","CD4.C","CD4.D","CD4.E","CD4.F","CD4.G","CD4.H","CD4.I","CD4.P","CD4.Q","CD4.R","CD4.S","CD4.T","CD4.U","CD4.wM","CD4.wN","CD4.wZ","CD4.X","CD4.Y","CD8.A","CD8.B","CD8.C","CD8.D","CD8.E","CD8.F","CD8.G","CD8.H","CD8.I","CD8.J","CD8.K","CD8.P","CD8.Q","CD8.R","CD8.S","CD8.T","CD8.wM","CD8.wU","CD8.wV","CD8.wX","CD8.wY","CD8aa.A","CD8aa.B","CD8aa.C","CD8aa.D","CD8aa.E","CD8aa.P","CD8aa.wM","DN.A","DN.B","DN.C","DN.D","DN.E","DN.F","DN.G","DN.H","DN.I","DN.J","DN.P","DN.wM","DP.wA","DP.wB","DP.wC","DP.wD","DP.wE","DP.wF","DP.wG","DP.wH","DP.wM","DP.wP","DP.wX","DP.wY","gdT.A","gdT.B","gdT.C","gdT.D","gdT.E","gdT.F","gdT.G","gdT.H","gdT.I","gdT.J","gdT.K","gdT.P","gdT.Q","gdT.R","gdT.S","gdT.T","gdT.U","gdT.V","gdT.wM","gdT.X","gdT.Z","Treg.A","Treg.B","Treg.C","Treg.D","Treg.E","Treg.F","Treg.P","Treg.wM","Tz.A","Tz.B","Tz.C","Tz.D","Tz.P","Tz.wM","nonT","thymocyte","unclear.cl14","unclear.cl25","unclear.cl37","unclear.cl47","unclear.miniverse","unclear.thymocyte");
	$immgenT_excluded_pops = array("nonT","thymocyte","unclear.cl14","unclear.cl25","unclear.cl37","unclear.cl47","unclear.miniverse","unclear.thymocyte");
	$key_pop_info_iTP = array_values(array_diff($key_pop_info_iTP_full, $immgenT_excluded_pops));

	// Whitelist: datagroup (as it appears in the URL) => expression table and its reference populations.
	// Male/Female has no reference list: its populations are read from the data-group table.
	$datagroups = array(
		'ImmGen%20ULI%20RNASeq' => array('ImmGen_ATAC_ULI_RNAseq_expr_20230510', $key_pop_info),
		'ImmGen%20MNP%20OpenSource' => array('ImmGen_OSMNP_ULI_RNAseq_expr_20200610', $key_pop_info_OSMNP),
		'ImmGen%20CGC' => array('ImmGen_CGC_RNAseq_expr_20210430', $key_pop_info_CGC),
		'ImmGen%20Cytokines%20Inflammatory' => array('ImmGen_Cytokines_Inflammatory_RNAseq_expr_20230727', $key_pop_info_IC),
		'immgenT%20Pseudobulk' => array('ImmGen_immgenT_Pseudobulk_RNAseq_expr', $key_pop_info_iTP),
		'Male/Female%20RNASeq' => array('ImmGen_RNAseq_expr', null),
	);
	if ($type != 'rnaseq') { skyline_fail(400, 'Invalid type'); }
	if (!array_key_exists($datagroup, $datagroups)) { skyline_fail(400, 'Invalid datagroup'); }
	list($rnaseq_table, $reference_pops) = $datagroups[$datagroup];

	$con = skyline_db_connect();

	if ($reference_pops === null) {
		$rnaseq_population_query = "SELECT population FROM ".skyline_ident($immgen_data_group)." WHERE data_group = ? ORDER BY `order`";
		$rnaseq_population_result = skyline_query($con, $rnaseq_population_query, array('Male/Female RNASeq'));
		$group_info = array();
		while ($r2 = mysqli_fetch_row($rnaseq_population_result)) {
			array_push($group_info, $r2[0]);
		}
	}
	else if ($key_population == 'Reference%20populations') {
		$group_info = $reference_pops;
	}
	else {
		$group_info = $pop_info;
	}

	// Population names become column names, so every one must be a real column of the expression table.
	// MySQL column names are case-insensitive (the page sends e.g. MC_SK_F for column MC_Sk_F), so match
	// without case and select the table's own spelling; the CSV header keeps the name as sent, as before.
	$table_columns = array();
	foreach (skyline_table_columns($con, $rnaseq_table) as $column) { $table_columns[strtolower($column)] = $column; }
	$select_columns = '';
	foreach ($group_info as $pop) {
		if (!isset($table_columns[strtolower($pop)])) { skyline_fail(400, 'Unknown population'); }
		$select_columns .= ", r." . skyline_ident($table_columns[strtolower($pop)]);
	}

	if ($reference_pops === null) {
		$rnaseq_expr_query = "SELECT r.Gene_Symbol".$select_columns." FROM ".skyline_ident($rnaseq_table)." AS r WHERE r.Gene_Symbol = ?";
	}
	else {
		$rnaseq_expr_query = "SELECT m.Gene_Symbol_MGI".$select_columns." FROM ".skyline_ident($rnaseq_table)." AS r, ".skyline_ident($rnaseq_meta_table)." AS m WHERE m.Gene_Symbol_MGI = ? AND m.Gene_Symbol = r.Gene_Symbol";
	}
	$expr_result = skyline_query($con, $rnaseq_expr_query, array($probeset_id));

	$headers = array('Gene_Symbol');


	$filename = "expr_table.csv";
	$delimiter = ",";

	// output headers so that the file is downloaded rather than displayed
	 //ob_clean();
	 header('Content-Type: application/csv');
	 header('Content-Disposition: attachment;filename='.$filename);

	// create a file pointer connected to the output stream
	$output = fopen('php://output', 'w');


	$outnames = array_merge($headers, $group_info);
	// output the column headings
	fputcsv($output, $outnames);
	//echo $filename;

	// loop over the rows, outputting them (numbers as MySQL printed them, as before)
	while ($row = skyline_fetch_text_row($expr_result, MYSQLI_NUM)) {
		fputcsv($output, $row);
	}
	exit;


?>
