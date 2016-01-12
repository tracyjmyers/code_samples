 /**
 * Tracy's Test2 Page
 */

 function gaiamtv_api_taxonomy_term_videos_tracy_test2($tid) {
  //print_r($tid);
  $nodes = array();
  $ret = new stdClass();

  list($p, $p_size) = _gaiamtv_api_fetch_pagination_values();
  $ret->currentPage = $p;
  $offset = $ret->currentPage * $p_size;

  //the sort order for the query string - most popular by default
  $sort = 'popular';

  if (!empty($_GET['sort']) && is_string($_GET['sort'])) {
    $sort = $_GET['sort'];
  }

  // Get production_status filter value.
  // TODO make this code reusable.
  $production_status = NULL;
  if (!empty($_GET['production_status']) && $_GET['production_status'] == 'active-production') {
    $production_status = 'active-production';
  }

  $sql_select = "SELECT DISTINCT n.nid FROM {node} n";
  $sql_join = " JOIN {term_node} tn ON n.vid = tn.vid LEFT JOIN {term_hierarchy} th ON tn.tid = th.tid LEFT JOIN {content_type_product_series} ctps ON n.vid = ctps.vid LEFT JOIN {content_field_subsite} cfs ON n.vid = cfs.vid LEFT JOIN {content_type_product_video} ctpv ON n.vid = ctpv.vid LEFT JOIN {content_field_feature_nid} cffn ON n.vid = cffn.vid ";
  if ($sort == 'popular') {
    $sql_join .= " LEFT JOIN {node} fnode ON cffn.field_feature_nid_nid = fnode.nid LEFT JOIN {smfplayer_node_counter_cached} smf_feature_counter ON fnode.nid = smf_feature_counter.nid LEFT JOIN {smfplayer_node_counter_cached} smf_node_counter ON n.nid = smf_node_counter.nid";
  }

  // Join for production_status filter.
  if (isset($production_status)) {
    $sql_join .= " LEFT JOIN {content_field_production_status} cfps ON n.vid = cfps.vid";
  }

  // Only published nodes of these types.
  // TODO Incorporate collection nodes.
  $types = array('product_video', 'product_season', 'product_series');
  $args = $types;
  $sql_where = " WHERE n.status = 1 AND n.type IN (" . db_placeholders($types, 'varchar') . ")";

  // Exclude these series subtypes..
  $s_subtypes = array('radio');
  $args = array_merge($args, $s_subtypes);
  $sql_where .= " AND (ctps.field_series_subtype_value NOT IN (" . db_placeholders($s_subtypes, 'varchar') . ") OR ctps.field_series_subtype_value IS NULL)";

  // and these video subtypes.
  $v_subtypes = array('episode', 'movie-segment', 'yoga-segment', 'fitness-segment', 'radio-episode', 'yoga-episode');
  $args = array_merge($args, $v_subtypes);
  $sql_where .= " AND (ctpv.field_video_subtype_value NOT IN (" . db_placeholders($v_subtypes, 'varchar') . ") OR ctpv.field_video_subtype_value IS NULL)";

  // Only include the current subsite and filter by this term id.
  $sql_where .= " AND cfs.field_subsite_value = %d AND (tn.tid = %d OR th.parent = %d)";
  $args = array_merge($args, array(gaiamtv_site_subsite_id(), $tid, $tid));

  // Apply production_status filter.
  if (isset($production_status)) {
    $sql_where .= " AND cfps.field_production_status_value = '%s'";
    $args = array_merge($args, array($production_status));
  }

  //Order videos based on sort - default is most popular ('popular')
  if ($sort == 'recent') {
    // Order by most recently added of video/series node and feature media combined
    $order_by = " ORDER BY n.created DESC";
  }
  else if ($sort == 'alpha') {
    //Order alphabetically
    $order_by = " ORDER BY n.title";
  }
  else {
    // Order by popularity of video/series node and feature media combined
    $order_by .= " ORDER BY COALESCE(smf_feature_counter.recentcount, smf_node_counter.recentcount) DESC";
  }

  $sql = $sql_select . $sql_join . $sql_where;
  $result = db_query_range($sql . $order_by, $args, $offset, $p_size);
  $count_sql = "SELECT COUNT(*) FROM (" . $sql . ") count_alias";
  $result_count = db_query($count_sql, $args);

  $ret->totalCount = db_result($result_count);

  while ($row = db_fetch_object($result)) {
    if ($node = node_load($row->nid)) {
      $nodes[] = _gaiamtv_api_get_node_info($node, TRUE);
    }
  }

  $ret->titles = $nodes;
  //print_r($ret);
  //return _gaiamtv_api_output_router($ret);

  //print_r($ret);
  /*
  id int
  title string
  teaser string
  body string
  updated int (unix timestamp)
  type string (always "video")
  series_id int
  season_num int
  episode_num int
  product_type string
  display_type string
  instructor_text string
  director_text string
  producer_text string
  writer_text string
  cast_text string
  copyright_text string
   * */
  $output = "<add>";
  foreach ($ret->titles as $video) {
    //echo " foreach ";
    //<doc><field name="id">change.me</field><field name="title">change.me</field></doc>
    $output .= "<doc>";
    $output .= "<field name='id'>" . $video->nid . "</field>";
	$title = str_replace("&", "&amp;", $video->title);
    $output .= "<field name='title'>" . $title . "</field>";
	$output .= "<field name='type'>" . $video->type . "</field>";
	$output .= "<field name='product_type'>" . $video->product_type . "</field>";
    $output .= "</doc>";
  }

  $output .= "</add>";
  print_r($output);

  return _gaiamtv_api_output_router($ret);
 }

