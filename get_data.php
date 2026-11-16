<?php
	session_start ();

	// Credentials (../config/db_config.php or SKYLINE_DB_* env vars) and the query helpers; see db_connect.php.
	require_once __DIR__ . '/db_connect.php';

	$probeset_id = skyline_post_string('probeset_id');
	$datagroup = skyline_post_string('datagroup');
	$key_population = skyline_post_string('key_population');
	$pop_info = isset($_POST['population']) ? explode(",", skyline_post_string('population')) : array();

	$key_pop_info = array('LTHSC_34-_BM','MMP2_150+48+_BM','proB_CLP_BM','proB_FrBC_BM','B_Fo_Sp','B_MZ_Sp','B_GC_CC_Sp','B_PC_Sp','B1b_PC','preT_DN1_Th','T_DP_Th','T_4_Nve_Sp','NKT_Sp','Treg_4_25hi_Sp','T_8_Nve_Sp','T8_TE_LCMV_d7_Sp','T8_Tem_LCMV_d180_Sp','Tgd_g2+d1_24a+_Th','Tgd_g2+d17_LN','Tgd_Sp','NK_27-11b+_Sp','ILC2_SI','ILC3_NKp46-CCR6-_SI','ILC3_NKp46+_SI','Mo_6C+II-_Bl','DC_8+_Sp','DC_pDC_Sp','GN_BM','GN_Thio_PC','MC_heparinase_PC','MF_PC','MF_RP_Sp','MF_microglia_CNS','MF_Alv_Lu','Ep_MEChi_Th','LEC_SLN','IAP_SLN','MAIT_Sp');
	$key_pop_info_OSMNP = array('MF_64pLYVEpIIp_Ao','MF_E10_5_YS','DC_24p8anXCR1n_Sp','DC_XCR1nSIRPap_Th','MF_115pICAM2p226n6Cn_PC','Mo_6Cp_Bl','MF_E16_5_PC','MF_KC_Clec4FpTim4p64p_Lv','MF_11cpSigFp_BAL','Mo_6Cp_Lu','MF_CCR2p64p6ClonGFPp_Ht','MF_45lo_CNS','MF_microglia_CNS','DC_8ap_PP','MF_DRG','MF_B220n6Cn64pTim4p_PC');
	$immgenT_excluded_pops = array('nonT','thymocyte','unclear.cl14','unclear.cl25','unclear.cl37','unclear.cl47','unclear.miniverse','unclear.thymocyte');

	// Whitelist: datagroup => tables, and the population filters for "Reference populations".
	// A filter is array(operator, populations) or null (all populations of the data group). 'ref_pop' applies to
	// the population list, 'ref_count' to the population-group count (they differ only for CGC, as before).
	$datagroups = array(
		'ImmGen ULI RNASeq' => array(
			'expr' => 'ImmGen_ATAC_ULI_RNAseq_expr_20230510',
			'group' => 'ImmGen_data_group_20230510',
			'global' => 'ImmGen_data_group_maximum_20191122',
			'ref_pop' => array('IN', $key_pop_info),
			'ref_count' => array('IN', $key_pop_info),
		),
		'ImmGen MNP OpenSource' => array(
			'expr' => 'ImmGen_OSMNP_ULI_RNAseq_expr_20200610',
			'group' => 'ImmGen_data_group_20230510',
			'global' => 'ImmGen_data_group_maximum_20191122',
			'ref_pop' => array('IN', $key_pop_info_OSMNP),
			'ref_count' => array('IN', $key_pop_info_OSMNP),
		),
		'ImmGen CGC' => array(
			'expr' => 'ImmGen_CGC_RNAseq_expr_20210430',
			'group' => 'ImmGen_data_group_CGC_20210430',
			'global' => 'ImmGen_data_group_CGC_maximum_20210430',
			'ref_pop' => array('NOT IN', array('MFrp.IL15')),
			'ref_count' => null,
		),
		'ImmGen Cytokines Inflammatory' => array(
			'expr' => 'ImmGen_Cytokines_Inflammatory_RNAseq_expr_20230727',
			'group' => 'ImmGen_data_group_Cytokines_Inflammatory_20230727',
			'global' => 'ImmGen_data_group_Cytokines_Inflammatory_maximum_20230727',
			'ref_pop' => null,
			'ref_count' => null,
		),
		'immgenT Pseudobulk' => array(
			'expr' => 'ImmGen_immgenT_Pseudobulk_RNAseq_expr',
			'group' => 'ImmGen_data_group_immgenT_Pseudobulk',
			'global' => 'ImmGen_data_group_immgenT_Pseudobulk_maximum',
			'ref_pop' => array('NOT IN', $immgenT_excluded_pops),
			'ref_count' => array('NOT IN', $immgenT_excluded_pops),
		),
	);
	if (!array_key_exists($datagroup, $datagroups)) { skyline_fail(400, 'Invalid datagroup'); }
	$ds = $datagroups[$datagroup];

	if ($key_population == 'Reference populations') {
		$pop_filter = $ds['ref_pop'];
		$count_filter = $ds['ref_count'];
	} else {
		$selected = ($datagroup == 'immgenT Pseudobulk') ? array_diff($pop_info, $immgenT_excluded_pops) : $pop_info;
		$pop_filter = array('IN', $selected);
		$count_filter = array('IN', $selected);
	}

	// " AND population IN (?, ...)" plus its values; the operator only ever comes from the table above.
	function population_where($filter) {
		if ($filter === null) { return array('', array()); }
		list($sql_list, $values) = skyline_in_list($filter[1]);
		return array(' AND population ' . $filter[0] . ' ' . $sql_list, $values);
	}
	list($pop_where, $pop_params) = population_where($pop_filter);
	list($count_where, $count_params) = population_where($count_filter);

	$immgen_data_group = skyline_ident($ds['group']);
	$immgen_data_group_global = skyline_ident($ds['global']);
	$rnaseq_table = skyline_ident($ds['expr']);
	$rnaseq_meta_table = '`ImmGen_RNAseq_meta_info_20200610`';
	$rnaseq_kegg_table = '`ImmGen_RNAseq_meta_info_Entrez2KEGG`';
	$rnaseq_orthology_table = '`ImmGen_RNAseq_meta_info_Human_Ortholog_20220121`';
	$rnaseq_go_table = '`ImmGen_RNAseq_meta_info_GO_20200610`';
	$rnaseq_biolegend_table = '`ImmGen_RNAseq_meta_info_BioLegend`';

	// Only server-side table names are concatenated below; every visitor value is a bound parameter.
	$rnaseq_population_query = "SELECT population, color, short_name, long_name, description, author, number_of_sample, sorting_info, color_unique_set FROM ".$immgen_data_group." WHERE data_group = ?".$pop_where." ORDER BY `order`";
	$rnaseq_population_group_query = "SELECT count(distinct(`population_group`)) FROM ".$immgen_data_group." WHERE data_group = ?".$count_where." ORDER BY `order`";
	$rnaseq_expr_query = "SELECT * FROM ".$rnaseq_table." AS r, ".$rnaseq_meta_table." AS m WHERE m.Gene_Symbol_MGI = ? AND m.Gene_Symbol_MGI = r.Gene_Symbol";
	$rnaseq_expr_global_query = "SELECT population, value FROM ".$immgen_data_group_global." WHERE data_group = ?";
	$rnaseq_meta_query = "SELECT * FROM ".$rnaseq_meta_table." WHERE Gene_Symbol_MGI = ?";
	$rnaseq_kegg_query = "SELECT k.* FROM ".$rnaseq_kegg_table." AS k, ".$rnaseq_meta_table." AS m WHERE m.Gene_Symbol_MGI = ? AND m.Entrez_Gene_ID = k.Entrez_Gene_ID";
	$rnaseq_go_query = "SELECT distinct(g.GO_ID), g.Category, g.GO_Name FROM ".$rnaseq_go_table." AS g, ".$rnaseq_meta_table." AS m WHERE m.Gene_Symbol_MGI = ? AND m.MGI_ID = g.MGI_ID ORDER BY g.Category DESC, g.GO_ID ASC";
	$rnaseq_orthology_query = "SELECT o.* FROM ".$rnaseq_orthology_table." AS o, ".$rnaseq_meta_table." AS m WHERE m.Gene_Symbol_MGI = ? AND m.MGI_ID = o.MGI_ID";
	$rnaseq_biolegend_query = "SELECT b.* FROM ".$rnaseq_biolegend_table." AS b, ".$rnaseq_orthology_table." AS o, ".$rnaseq_meta_table." AS m WHERE m.Gene_Symbol_MGI = ? AND m.MGI_ID = o.MGI_ID AND o.Human_EntrezID = b.Human_EntrezID";

	$con = skyline_db_connect();

	$rnaseq_population_result = skyline_query($con, $rnaseq_population_query, array_merge(array($datagroup), $pop_params));
	$rnaseq_population_group_result = skyline_query($con, $rnaseq_population_group_query, array_merge(array($datagroup), $count_params));
	$rnaseq_expr_result = skyline_query($con, $rnaseq_expr_query, array($probeset_id));
	$rnaseq_expr_global_result = skyline_query($con, $rnaseq_expr_global_query, array($datagroup));
	$rnaseq_columns = skyline_table_columns($con, $ds['expr']);
	$rnaseq_meta_result = skyline_query($con, $rnaseq_meta_query, array($probeset_id));
	$rnaseq_kegg_result = skyline_query($con, $rnaseq_kegg_query, array($probeset_id));
	$rnaseq_go_result = skyline_query($con, $rnaseq_go_query, array($probeset_id));
	$rnaseq_orthology_result = skyline_query($con, $rnaseq_orthology_query, array($probeset_id));
	$rnaseq_biolegend_result = skyline_query($con, $rnaseq_biolegend_query, array($probeset_id));

	// Position of each expression-table column in the SELECT * row (the expression table's columns come first).
	$rnaseq_column_index = array_flip($rnaseq_columns);

	$rnaseq_expr = skyline_fetch_text_row($rnaseq_expr_result, MYSQLI_BOTH);

	$rnaseq_max = 0;
	while ($r2 = skyline_fetch_text_row($rnaseq_expr_global_result, MYSQLI_NUM)) {
		if(floatval($r2[1]) > $rnaseq_max){
			$rnaseq_max = floatval($r2[1]);
		}
	}

	$rnaseq_meta = skyline_fetch_text_row($rnaseq_meta_result, MYSQLI_ASSOC);

	$kegg = array();
	while ($r3 = skyline_fetch_text_row($rnaseq_kegg_result, MYSQLI_NUM)) {
		array_push($kegg, array('kegg_id' => $r3[1], 'kegg_name' => $r3[2]));
	}

	$go = array();
	while ($r4 = skyline_fetch_text_row($rnaseq_go_result, MYSQLI_NUM)) {
		array_push($go, array('go_id' => $r4[0], 'go_category' => $r4[1], 'go_name' => $r4[2]));
	}

	if(mysqli_num_rows($rnaseq_orthology_result) > 0 ) { $orthology = skyline_fetch_text_row($rnaseq_orthology_result, MYSQLI_ASSOC); } else { $orthology = 'None'; }
	if(mysqli_num_rows($rnaseq_biolegend_result) > 0 ) { $biolegend = skyline_fetch_text_row($rnaseq_biolegend_result, MYSQLI_ASSOC); } else { $biolegend = 'None'; }

	$r5 = skyline_fetch_text_row($rnaseq_population_group_result, MYSQLI_NUM);
	$ranseq_population_group_num = $r5[0];

	$json = array();
	while ($r = skyline_fetch_text_row($rnaseq_population_result, MYSQLI_NUM)) {
		if($datagroup == 'immgenT Pseudobulk' && in_array($r[0], $immgenT_excluded_pops)) { continue; }
		// Fallbacks for datasets where short_name/long_name/other fields are NULL
		$short_name      = $r[2] !== null && $r[2] !== '' ? $r[2] : $r[0];
		$long_name       = $r[3] !== null && $r[3] !== '' ? $r[3] : $short_name;
		$description     = $r[4] !== null ? $r[4] : '';
		$author          = $r[5] !== null ? $r[5] : '';
		$number_of_sample = $r[6] !== null ? $r[6] : 0;
		$sorting_info    = $r[7] !== null ? $r[7] : 0;
		$color_unique_set = $r[8];

		$value_index = $rnaseq_column_index[$r[0]] ?? null;
		array_push(
			$json,
			array(
				'population'      => $r[0],
				'start'           => 0,
				'value'           => floatval($value_index !== null && $rnaseq_expr !== null ? $rnaseq_expr[$value_index] : 0),
				'color'           => $r[1],
				'short_name'      => $short_name,
				'long_name'       => $long_name,
				'description'     => $description,
				'author'          => $author,
				'number_of_sample'=> $number_of_sample,
				'sorting_info'    => $sorting_info,
				'color_unique_set'=> $color_unique_set
			)
		);
	}
	mysqli_close($con);

	$output = array('data'=>$json, 'meta'=>$rnaseq_meta, 'kegg'=>$kegg, 'go'=>$go, 'orthology'=>$orthology, 'biolegend'=>$biolegend,  'max'=>$rnaseq_max, 'pop_group_num'=>$ranseq_population_group_num);
	echo json_encode($output);

?>
