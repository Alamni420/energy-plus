<?php

/**
* EnergyPlus Orders
*
* Store reports
*
* @since      1.0.0
* @package    EnergyPlus
* @subpackage EnergyPlus/framework
* @author     EN.ER.GY <support@en.er.gy>
* */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class EnergyPlus_Reports extends EnergyPlus {

	public static $store = array();
	public static $zero = '0';

	/**
	* Starts everything
	*
	* @return null
	*/

	public static function run() {

		EnergyPlus::wc_engine();

		wp_enqueue_script("energyplus-funnel-graph",  EnergyPlus_Public . "3rd/funnel-graph/js/funnel-graph.js");
		wp_enqueue_script("energyplus-chart",     EnergyPlus_Public . "3rd/chart.js", array(), EnergyPlus_Version);
		wp_enqueue_script("energyplus-reports",  EnergyPlus_Public . "js/energyplus-reports.js", array(), EnergyPlus_Version);

		self::route();
	}


	/**
	* Router for sub pages
	*
	* @return null
	*/

	private static function route() {

		switch (EnergyPlus_Helpers::get('action')) {

			case 'woocommerce':
			echo EnergyPlus_View::run('reports/woocommerce',  array('report'=>EnergyPlus_Helpers::get('report', '')));
			break;

			case 'import':
			self::import();
			break;

			default:
			self::index();
			break;
		}
	}

	/**
	* Ajax router
	*
	* @since  1.0.0
	* @return EnergyPlus_Ajax
	*/

	public static function ajax() {

		$do        = EnergyPlus_Helpers::post('do') ;

		switch ($do)
		{
			case 'import':
			self::import();
			break;

			case 'manual':
			self::manual();
			break;
		}
	}


	/**
	* Main function
	*
	* @param  mixed $filter   array of filter
	*
	* @return EnergyPlus_View
	*/

	public static function index() {
		global $wpdb;

		if (EnergyPlus_Helpers::get('graph')) {
			EnergyPlus::option('reports-graph', (string)intval(EnergyPlus_Helpers::get('graph', "2")), 'set');
		}

		// Delete transients
		//wc_delete_shop_order_transients();

		$data                 = array();
		$range                = EnergyPlus_Helpers::get('range', 'daily');
		$data['results']      = self::energyplus_data(array('range'=>$range));

		switch ($range) {

			case 'yearly':

			$data['quick'][0] = array('title'=>__("This Year's Sales", 'energyplus'), 'text'=> wc_price($data['results'][EnergyPlus_Helpers::strtotime('now', 'Y')]['total_sales']));
			if (isset($data['results'][EnergyPlus_Helpers::strtotime('last year', 'Y')]['total_sales'])) {
				$data['quick'][1] = array('title'=>__("Last Year's Sales", 'energyplus'), 'text'=> wc_price($data['results'][EnergyPlus_Helpers::strtotime('last year', 'Y')]['total_sales']));
			}
			$data['quick'][2] = array('title'=>__("Average Sales", 'energyplus'), 'text'=> wc_price(end($data['results'])['average_sales']));

			$result_key = EnergyPlus_Helpers::strtotime('now', 'Y');

			break;


			case 'monthly':

			if (12 === (int)EnergyPlus_Helpers::strtotime('last month', 'm')) {
				global $wpdb;
				$_result = $wpdb->get_results(
					$wpdb->prepare("
					SELECT
					day,
					SUM(visitors) AS visitors,
					SUM(sales) AS sales,
					SUM(orders) AS orders,
					SUM(customers) AS customers,
					SUM(net_sales) AS net_sales,
					SUM(total_discount) AS total_discount,
					SUM(total_shipping) AS total_shipping,
					SUM(total_refunds) AS total_refunds
					FROM {$wpdb->prefix}energyplus_daily
					WHERE type = %s AND day = '%s' ORDER BY day ASC",
					'M', EnergyPlus_Helpers::strtotime('last month', 'Ym')
				), ARRAY_A
			);
			if (!is_wp_error($_result)) {
				foreach ($_result AS $r) {
					$data['results'][$r['day']]  = $r;
				}
			}
		}

		$data['quick'] = array(
			0 => array('title'=>__("This Month's Sales", 'energyplus'), 'text'=> wc_price($data['results'][EnergyPlus_Helpers::strtotime('now', 'Ym')]['total_sales'])),
			1 => array('title'=>__("Last Month's Sales", 'energyplus'), 'text'=> wc_price($data['results'][EnergyPlus_Helpers::strtotime('last month', 'Ym')]['total_sales'])),
			2 => array('title'=>__("Average Sales", 'energyplus'), 'text'=> wc_price(end($data['results'])['average_sales'])),
		);

		if (12 === (int)EnergyPlus_Helpers::strtotime('last month', 'm')) {
			if (isset($data['results'][EnergyPlus_Helpers::strtotime('last month', 'Ym')])) {
				unset($data['results'][EnergyPlus_Helpers::strtotime('last month', 'Ym')]);
			}
		}

		$result_key = EnergyPlus_Helpers::strtotime('now', 'Ym');

		break;


		case 'weekly':

		$data['quick'] = array(
			0 => array('title'=>__("This Week's Sales", 'energyplus'), 'text'=> wc_price($data['results'][EnergyPlus_Helpers::strtotime('now', 'YW')]['total_sales'])),
			1 => array('title'=>__("Last Week's Sales", 'energyplus'), 'text'=> wc_price($data['results'][EnergyPlus_Helpers::strtotime('last week', 'YW')]['total_sales'])),
			2 => array('title'=>__("Average Sales", 'energyplus'), 'text'=> wc_price(end($data['results'])['average_sales'])),
		);

		$result_key = EnergyPlus_Helpers::strtotime('now', 'YW');

		break;

		case 'daily':

		if (!EnergyPlus_Helpers::get('month')) {
			$data['quick'][0] = array('title'=>__("Today's Sales", 'energyplus'), 'text'=> wc_price($data['results'][EnergyPlus_Helpers::strtotime('now', 'Ymd')]['total_sales']));
			if (isset($data['results'][EnergyPlus_Helpers::strtotime('yesterday', 'Ymd')]['total_sales'])) {
				$data['quick'][1] = array('title'=>__("Yesterday's Sales", 'energyplus'), 'text'=> wc_price($data['results'][EnergyPlus_Helpers::strtotime('yesterday', 'Ymd')]['total_sales']));
			}
			$data['quick'][2] = array('title'=>__("Average Sales", 'energyplus'), 'text'=> wc_price(end($data['results'])['average_sales']));
		}

		$result_key = EnergyPlus_Helpers::strtotime('now', 'Ymd');

	}

	$funnel_order         = (isset($data['results'][$result_key]['orders_count'])) ? intval($data['results'][$result_key]['orders_count']) : 0;
	$funnel_visitors      = (isset($data['results'][$result_key]['visitors'])) ? intval($data['results'][$result_key]['visitors']) : 0;
	$funnel_product_pages = (isset($data['results'][$result_key]['product_pages'])) ? intval($data['results'][$result_key]['product_pages']) : 0;
	$funnel_carts         = (isset($data['results'][$result_key]['carts'])) ? intval($data['results'][$result_key]['carts']) : 0;
	$funnel_checkout      = (isset($data['results'][$result_key]['checkout'])) ? intval($data['results'][$result_key]['checkout']) : 0;

	if (0 === $funnel_visitors) {
		$funnel_visitors = '0.0001'; // Prevent graph error
	}

	$data['funnel']       = array($funnel_visitors, $funnel_product_pages, $funnel_carts, $funnel_checkout, $funnel_order);


	echo EnergyPlus_View::run('reports/overview',  array( 'data'=>$data ));
}

/**
* Get reports data from energyplus_daily table
*
* @since  1.0.0
* @param  array     $args [description]
*/

public static function energyplus_data($args = array()) {
	global $wpdb;

	$request = new WP_REST_Request( 'GET', '/wc-analytics/reports/revenue/stats' );

	switch ($args['range']) {

		case 'yearly':

		$_first_order_date = $wpdb->get_results(
			$wpdb->prepare("
			SELECT {$wpdb->prefix}posts.*
			FROM {$wpdb->prefix}posts
			WHERE post_type = %s ORDER BY post_date ASC LIMIT 1",
			'shop_order'
		), ARRAY_A );

		if (isset($_first_order_date[0])) {
			$start_date = $day_start  = EnergyPlus_Helpers::strtotime($_first_order_date[0]['post_date'], 'Y');
		} else {
			$start_date =  $day_start  = EnergyPlus_Helpers::strtotime('first day of january  00:00:00', 'Y');
		}


		$type       = 'Y';
		$label      = 'y';
		$day_end    = EnergyPlus_Helpers::strtotime('last day of december', 'Y');
		$goal       = EnergyPlus::option('feature-goals-yearly',0);
		$request->set_param( 'interval', 'year' );

		break;

		case 'monthly':

		$type       = 'M';
		$label      = 'm';
		$start_date = EnergyPlus_Helpers::strtotime('first day of january','Y-m-d\T00:00:00');
		$end_date = EnergyPlus_Helpers::strtotime('now','Y-m-d\T23:59:59');
		$day_start  = EnergyPlus_Helpers::strtotime('first day of january', 'Ym');
		$day_end    = EnergyPlus_Helpers::strtotime('now', 'Ym');
		$goal       = EnergyPlus::option('feature-goals-monthly',0);
		$request->set_param( 'interval', 'month' );

		break;

		case 'weekly':

		$type       = 'W';
		$label      = 'W';
		$start_date = EnergyPlus_Helpers::strtotime('first day of january','Y-m-d\T00:00:00');
		$end_date = EnergyPlus_Helpers::strtotime('now','Y-m-d\T23:59:59');
		$day_start  = date('Y01');
		$day_end    = EnergyPlus_Helpers::strtotime('now', 'YW');
		$goal       = EnergyPlus::option('feature-goals-weekly',0);
		$request->set_param( 'interval', 'week' );

		break;

		case 'daily':

		$type       = 'D';
		$label      = 'd l';
		$start_date = EnergyPlus_Helpers::strtotime('first day of this month','Y-m-d\T00:00:00');
		$end_date = EnergyPlus_Helpers::strtotime('now','Y-m-d\T23:59:59');
		$day_start  = EnergyPlus_Helpers::strtotime('first day of this month', 'Ymd');
		$day_end    = EnergyPlus_Helpers::strtotime('now', 'Ymd');
		$goal       = EnergyPlus::option('feature-goals-daily',0);

		if (EnergyPlus_Helpers::get('month')) {
			$start_date = date("Y-m-01\T00:00:00", strtotime("-" . (int)EnergyPlus_Helpers::get('month')." month") );
			$day_start  = date("Ym01", strtotime("-" . (int)EnergyPlus_Helpers::get('month')." month") );
			$day_end    = date("Ymt", strtotime("-" . (int)EnergyPlus_Helpers::get('month')." month") );
		}

		$request->set_param( 'interval', 'day' );

		break;
	}

	$request->set_param( 'per_page', 100 );
	$request->set_param( 'after', $start_date );
	$request->set_param( 'before', $end_date );
	$request->set_param( 'order', 'asc');
	$_response = rest_do_request( $request );

	if ( is_wp_error( $_response ) || (isset($_response->status) && 200 !== $_response->status) ) {
		echo 'API ERROR:<br>';
		print_r($_response);
		wp_die();
	}

	$response = $_response->get_data();

	$_result = $wpdb->get_results(
		$wpdb->prepare("
		SELECT {$wpdb->prefix}energyplus_daily.*
		FROM {$wpdb->prefix}energyplus_daily
		WHERE type = %s AND day >= %s ORDER BY day ASC",
		$type, $day_start
	), ARRAY_A
);

foreach ($_result AS $r) {
	$result[$r['day']]          = $r;
	$result[$r['day']]['day']   = $r['day'];
	$result[$r['day']]['label'] = strtoupper(date_i18n($label,  strtotime($r['day'])));
}


if (isset($response['intervals'])) {
	foreach ($response['intervals'] AS $intervals) {

		$interval = str_replace('-', '', $intervals['interval']);

		foreach ($intervals['subtotals'] AS $k=>$v) {
			$result[$interval][$k] = $v;
		}

		$result[$interval]['day']   = $interval;
		$result[$interval]['goal']   = $goal;
		$result[$interval]['checkout']       = 0;
		$result[$interval]['product_pages']  = 0;
		$result[$interval]['orders']         = 0;
		$result[$interval]['label'] = strtoupper(date_i18n($label,  strtotime($interval)));
	}

	$params = $request->get_query_params();
	$extend = apply_filters('energyplus_extends_reports_data', $params['interval'], $start_date, $end_date);
	if (is_array($extend)) {
		$result = $result + $extend;
	}

}

if ('yearly' === $args['range']) {
	$_result = $wpdb->get_results(
		$wpdb->prepare("
		SELECT
		day,
		SUM(visitors) AS visitors,
		SUM(sales) AS sales,
		SUM(orders) AS orders,
		SUM(customers) AS customers,
		SUM(net_sales) AS net_sales,
		SUM(total_discount) AS total_discount,
		SUM(total_shipping) AS total_shipping,
		SUM(total_refunds) AS total_refunds
		FROM {$wpdb->prefix}energyplus_daily
		WHERE type = %s AND day LIKE '2020%' ORDER BY day ASC",
		'M'
	), ARRAY_A
);
foreach ($_result AS $r) {
	$result[2020]          = $r;
	$result[2020]['day']   = 2020;
	$result[2020]['label'] = strtoupper(date_i18n($label,  strtotime('2020')));
}
}


$average_sales = 0;
$prev          = 0;
$graph         = 2;
$i             = 0;


if ("1" === EnergyPlus::option('reports-graph', "2")) {
	$graph = "1";
}

for ($x = $day_start; $x <= $day_end; ++$x) {

	++$i;

	if ('weekly' === $args['range']) {
		$dto = new DateTime();
		$dto->setISODate(date('Y'), $i);
		$day2 = date_i18n("d M", strtotime($dto->format('Y-m-d'))+(0*24*60*60));
	} else 	if ('daily' === $args['range']) {

		if ("1" === $graph) {
			$day2 =	date_i18n('d',  strtotime($x));
		} else {
			$day2 =	date_i18n('d D',  strtotime($x));
			if (EnergyPlus_Helpers::get('month')) {
				$day2 =	date_i18n('d M - D',  strtotime($x));
			}
		}

	} else 	if ('monthly' === $args['range']) {
		$day2 =	date_i18n('F',  strtotime($x."01"));
	} else 	if ('yearly' === $args['range']) {
		$day2 =	date_i18n('Y',  strtotime($x."-01-01"));
	}



	if (!isset($result[$x])) {
		$results[$x] = array('day' => $x,
		'average_sales'            => ($average_sales/$i),
		'goal'                     => $goal,
		'customers'                => 0,
		'carts'                    => 0,
		'checkout'                 => 0,
		'product_pages'            => 0,
		'orders'                   => 0,
		'net_sales'                => 0,
		'total_refunds'            => 0,
		'total_shipping'           => 0,
		'total_tax'                => 0,
		'total_discount'           => 0,
		'sales'                    => static::$zero,
		'visitors'                 => static::$zero,
		'label'                    => strtoupper($day2),
		'prev'                     => 0
	);
} else {
	$average_sales               += $result[$x]['total_sales'];
	if (!isset($result[$x]['visitors'])) {
		$result[$x]['visitors'] = static::$zero;
	}
	$result[$x]['label']          = strtoupper($day2);
	$result[$x]['average_sales']  = $average_sales/$i;
	$result[$x]['prev']           = $prev;
	$results[$x]                  = $result[$x];
}
if (isset($result[$x]['total_sales'])) {
	$prev = $result[$x]['total_sales'];
}

}

if ((int)EnergyPlus_Helpers::get('month') === 0) {
	$results = self::energyplus_data_today($args['range'], $results);
}

return $results;
}

/**
* Get live reports which are not saved to database yet
*
* @since  1.0.0
*/

public static function energyplus_data_today($range, $results) {

	global $wpdb;

	if ('daily' === $range) {

		$key       = date('Ymd', current_time('timestamp'));
		$day_start = EnergyPlus_Helpers::strtotime('now', 'Y-m-d 00:00:00');
		$day_end   = EnergyPlus_Helpers::strtotime('now', 'Y-m-d H:i:s');

	}  else if ('weekly' === $range) {

		$key                    = date('YW', current_time('timestamp'));
		$results[$key]['label'] = strtoupper(EnergyPlus_Helpers::strtotime('now', 'd M'));
		$day_start              = EnergyPlus_Helpers::strtotime('monday this week', 'Y-m-d 00:00:00');
		$day_end                = EnergyPlus_Helpers::strtotime('now', 'Y-m-d H:i:s');

	}else if ('monthly' === $range) {

		$key       = date('Ym', current_time('timestamp'));
		$day_start = EnergyPlus_Helpers::strtotime('first day of this month', 'Y-m-d 00:00:00');
		$day_end   = EnergyPlus_Helpers::strtotime('now', 'Y-m-d H:i:s');

	}else if ('yearly' === $range) {

		$key       = date('Y', current_time('timestamp'));
		$day_start = EnergyPlus_Helpers::strtotime('first day of january', 'Y-m-d 00:00:00');
		$day_end   = EnergyPlus_Helpers::strtotime('last day of december', 'Y-m-d 00:00:00');

	}


	$_visitors = $wpdb->get_results(
		$wpdb->prepare("
		SELECT type, count(distinct session_id) as count
		FROM {$wpdb->prefix}energyplus_requests
		WHERE date >= %s AND date <= %s
		GROUP By type",
		$day_start, $day_end
	),
	ARRAY_A
);

$_visitors_all = $wpdb->get_var(
	$wpdb->prepare("
	SELECT  count(distinct session_id) as count
	FROM {$wpdb->prefix}energyplus_requests
	WHERE date >= %s AND date <= %s",
	$day_start, $day_end
	)
);

$results[$key]['visitors'] = $_visitors_all;

foreach ($_visitors AS $value) {

	if ("1" === $value['type']) {
		if (!isset($results[$key]['product_pages'])) {
			$results[$key]['product_pages'] = 0;
		}

		$results[$key]['product_pages'] += $value['count'];

	}

	if ("4" === $value['type']) {
		if (!isset($results[$key]['carts'])) {
			$results[$key]['carts'] = 0;
		}

		$results[$key]['carts'] += $value['count'];
	}

	if ("6" === $value['type']) {
		if (!isset($results[$key]['checkout'])) {
			$results[$key]['checkout'] = 0;
		}
		$results[$key]['checkout'] += $value['count'];
	}

}

if (!isset($results[$key]['product_pages'])) {
	$results[$key]['product_pages'] = 0;
}

if (!isset($results[$key]['carts'])) {
	$results[$key]['carts'] = 0;
}

if (!isset($results[$key]['checkout'])) {
	$results[$key]['checkout'] = 0;
}


/*
$request = new WP_REST_Request( 'GET', '/wc-analytics/reports/revenue/stats' );
$request->set_param( 'per_page', 100 );
$request->set_param( 'after', date('Y-m-d\T00:00:00', strtotime($day_start)));
$request->set_param( 'before', date('Y-m-d\T00:00:00', strtotime($day_end)));
$request->set_param( 'order', 'asc');
$_response = rest_do_request( $request );
if ( is_wp_error( $_response ) ) {
//	return $response;
}
$response = $_response->get_data();

if ($responsex) {

$results[$key]['total_sales']          += $response['totals']['total_sales'];
$results[$key]['orders_count']         = $response['totals']['orders_count'];
$results[$key]['total_customers']      += $response['totals']['total_customers'];
$results[$key]['coupons'] += $response['totals']['coupons'];
$results[$key]['taxes']      += $response['totals']['taxes'];
$results[$key]['shipping'] += $response['totals']['shipping'];
$results[$key]['refunds']  += $response['totals']['refunds'];
$results[$key]['net_revenue']      += $response['totals']['net_revenue'];
}
*/
return $results;
}


/**
* Daily cron for insert stats to energyplus_daily table
*
* @since  1.0.0
*/

public static function cron_daily($args = array()) {
	global $wpdb;

	EnergyPlus::wc_engine();

	//include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php' );
	//include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-report-sales-by-date.php' );

	foreach (array('D', 'W', 'M') AS $type) {

		//$report = new WC_Report_Sales_By_Date();

		$_GET['start_date'] = null;
		$_GET['end_date'] = null;

		if ('D' === $type){
			$_GET['start_date'] = EnergyPlus_Helpers::strtotime('yesterday', 'Y-m-d');
			$_GET['end_date']   = EnergyPlus_Helpers::strtotime('yesterday', 'Y-m-d');
		}

		if ('W' === $type && "1" === EnergyPlus_Helpers::strtotime('now', 'N')) {
			$_GET['start_date'] = EnergyPlus_Helpers::strtotime('now - 7 days', 'Y-m-d');
			$_GET['end_date']   = EnergyPlus_Helpers::strtotime('yesterday', 'Y-m-d');
		}

		if ('M' === $type && "1" === EnergyPlus_Helpers::strtotime('now','j')) {
			$_GET['start_date'] = EnergyPlus_Helpers::strtotime('first day of last month', 'Y-m-d');
			$_GET['end_date']   = EnergyPlus_Helpers::strtotime('first day of this month', 'Y-m-d');
		}

		if (!isset($_GET['start_date'])) {
			continue;
		}

		/*$report_data     = $report->calculate_current_range( 'custom' );

		if (is_wp_error($report_data)) {
		return;
	}*/

	switch ($type) {
		case 'D':

		$day             = EnergyPlus_Helpers::strtotime('yesterday', 'Ymd');
		//$report_data     = $report->get_report_data();
		$result['goal']  = floatval(EnergyPlus::option('feature-goals-daily', 0));
		$strtotime_start = EnergyPlus_Helpers::strtotime('yesterday', "Y-m-d 00:00:00");
		$strtotime_end   = EnergyPlus_Helpers::strtotime('today', "Y-m-d 00:00:00");
		break;

		case 'W':

		$day             = EnergyPlus_Helpers::strtotime('monday last week', 'YW');
		//$report_data     = $report->get_report_data();
		$result['goal']  = floatval(EnergyPlus::option('feature-goals-weekly', 0));
		$strtotime_start = EnergyPlus_Helpers::strtotime('monday last week', "Y-m-d 00:00:00");
		$strtotime_end   = EnergyPlus_Helpers::strtotime('monday this week', 'Y-m-d 00:00:00');

		break;

		case 'M':

		$day             = EnergyPlus_Helpers::strtotime('first day of last month', 'Ym');
		//$report_data     = $report->get_report_data();
		$result['goal']  = floatval(EnergyPlus::option('feature-goals-monthly', 0));
		$strtotime_start = EnergyPlus_Helpers::strtotime('first day of last month', "Y-m-d 00:00:00");
		$strtotime_end   = EnergyPlus_Helpers::strtotime('first day of this month', "Y-m-d 00:00:00");

		break;

	}

	/*$result['sales']          = floatval($report_data->total_sales);
	$result['orders']         = intval  ($report_data->total_orders);
	$result['customers']      = intval  ($report_data->total_customers);
	$result['net_sales']      = floatval($report_data->net_sales);
	$result['total_discount'] = floatval($report_data->total_coupons);
	$result['total_tax']      = floatval($report_data->total_tax);
	$result['total_shipping'] = floatval($report_data->total_shipping);
	$result['total_refunds']  = floatval($report_data->total_refunds);*/


	// Visitors
	$visitors = $wpdb->get_var(
		$wpdb->prepare("
		SELECT count(distinct session_id) as counts
		FROM {$wpdb->prefix}energyplus_requests
		WHERE date >= %s AND date <= %s",
		$strtotime_start,$strtotime_end
		)
	);


	$result['visitors'] = intval($visitors);

	/////////////
	//////

	$insert = $wpdb->insert( $wpdb->prefix."energyplus_daily",
	array(
		'type'           => $type,
		'day'            => $day,
		'visitors'       => $result['visitors'],
		'sales'          => 0,
		'orders'         => 0,
		'customers'      => 0,
		'goal'           => 0,
		'net_sales'      => 0,
		'total_discount' => 0,
		'total_tax'      => 0,
		'total_shipping' => 0,
		'total_refunds'  => 0,
		'updated_at'     => current_time('mysql')
	),
	array('%s', '%s','%f', '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%s')
);
}

// delete unnecessary records
$wpdb->query(
	$wpdb->prepare("
	DELETE
	FROM {$wpdb->prefix}energyplus_requests
	WHERE date <= %s LIMIT %d",
	date('Y-m-d 00:00:00', strtotime("-40 day")), (1000+(date('z')*20+1))
	)
);
}

/**
* Import old reports to energyplus_daily table
*
* @since  1.1.0
*/

public static function import($args = array()) {
	global $wpdb;

	EnergyPlus::wc_engine();

	if ('import-date' === EnergyPlus_Helpers::post('sub')) {

		include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php' );
		include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-report-sales-by-date.php' );

		$first = EnergyPlus_Helpers::strtotime('today', 'Y-m-01');
		$date = EnergyPlus_Helpers::strtotime(EnergyPlus_Helpers::post('range'), 'Y-m-01');

		if (strtotime($date) < strtotime($first)) {
			EnergyPlus_Ajax::success(array('type' => 'import-week', 'date' => EnergyPlus_Helpers::strtotime("today", "Y-m-d"), 'det'=> '<div class="__A__x __A__'.EnergyPlus_Helpers::strtotime("$date - 1 month", "Y-m-01").'"><span class="dashicons dashicons-yes-alt text-success"></span><br>#2 imported successfully</div>'));
			return;
		}

		for ($i = 1; $i<date('d'); ++$i) {
			$i = sprintf("%02d", $i);
			$report = new WC_Report_Sales_By_Date();

			$_GET['start_date'] = EnergyPlus_Helpers::strtotime($date, "Y-m-$i");
			$_GET['end_date']   = EnergyPlus_Helpers::strtotime($date, "Y-m-$i");

			$report_data     = $report->calculate_current_range( 'custom' );

			if (is_wp_error($report_data)) {
				return;
			}

			$report_data     = $report->get_report_data();

			if (date("Ym$i") <> date("Ymd")) {
				self::import_db('D', date("Ym$i"), $report_data);
			}

		}

		EnergyPlus_Ajax::success(array('type' => 'import-date', 'date' => EnergyPlus_Helpers::strtotime("$date - 1 month", "Y-m-01"), 'det'=> '<div class="__A__x __A__'.EnergyPlus_Helpers::strtotime("$date - 1 month", "Y-m-01").'"><span class="dashicons dashicons-yes-alt text-success"></span><br>#1 imported successfully</div>'));


	} else if ('import-week' === EnergyPlus_Helpers::post('sub')) {
		include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php' );
		include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-report-sales-by-date.php' );

		$first = EnergyPlus_Helpers::strtotime('today', 'Y-01-01');
		$date = EnergyPlus_Helpers::strtotime(EnergyPlus_Helpers::post('range'), 'Y-m-d');

		$week = EnergyPlus_Helpers::strtotime(EnergyPlus_Helpers::post('range'), 'W');
		$year = EnergyPlus_Helpers::strtotime(EnergyPlus_Helpers::post('range'), 'Y');

		if (strtotime($date) <= strtotime($first)) {
			EnergyPlus_Ajax::success(array('type' => 'import-month', 'date' => EnergyPlus_Helpers::strtotime('today', "Y-m-d"), 'det'=> '<div class="__A__x __A__'.EnergyPlus_Helpers::strtotime("today", "Y-m-d").'"><span class="dashicons dashicons-yes-alt text-success"></span><br>#' . EnergyPlus_Helpers::strtotime($date, "m"). ' imported successfully</div>'));
			return;
		}

		$week_start =  date('Y-m-d', strtotime("$year-W$week-1"));
		$week_end =  date('Y-m-d', strtotime("$year-W$week-7"));

		$report = new WC_Report_Sales_By_Date();

		$_GET['start_date'] = $week_start;
		$_GET['end_date']   = $week_end;

		$report_data     = $report->calculate_current_range( 'custom' );

		if (is_wp_error($report_data)) {
			return;
		}

		$report_data     = $report->get_report_data();

		if ("$year$week" <> date("YW")) {
			self::import_db('W',  $year.''.$week, $report_data);
		}

		EnergyPlus_Ajax::success(array('type' => 'import-week', 'date' => EnergyPlus_Helpers::strtotime("$week_start - 3 day", "Y-m-d"), 'det'=> '<div class="__A__x __A__'.EnergyPlus_Helpers::strtotime("$week_start - 3 day", "Y-m-d").'"><span class="dashicons dashicons-yes-alt text-success"></span><br>#' . EnergyPlus_Helpers::strtotime($date, "W"). ' imported successfully</div>'));
	} else if ('import-month' === EnergyPlus_Helpers::post('sub')) {

		include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php' );
		include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-report-sales-by-date.php' );

		$first = EnergyPlus_Helpers::strtotime('today', 'Y-01-01');
		$date = EnergyPlus_Helpers::strtotime(EnergyPlus_Helpers::post('range'), 'Y-m-d');

		$lastday = EnergyPlus_Helpers::strtotime(EnergyPlus_Helpers::post('range'), 't');
		$month = EnergyPlus_Helpers::strtotime(EnergyPlus_Helpers::post('range'), 'm');
		$year = EnergyPlus_Helpers::strtotime(EnergyPlus_Helpers::post('range'), 'Y');

		if (strtotime($date) <= strtotime($first)) {
			EnergyPlus_Ajax::success(array('type' => 'import-ok', 'date' => "-1", 'det'=> '<div class="__A__x __A__-1"><span class="dashicons dashicons-yes-alt text-success"></span><br>Completed!</div>'));
			//	EnergyPlus_Ajax::success(array('type' => 'import-year', 'date' => EnergyPlus_Helpers::strtotime($date, "Y-m-d"), 'det'=> '<div class="__A__x __A__'.EnergyPlus_Helpers::strtotime("today", "Y-m-d").'"><span class="dashicons dashicons-yes-alt text-success"></span><br>#' . EnergyPlus_Helpers::strtotime($date, "m"). ' imported successfully</div>'));
			return;
		}

		$month_start =  date('Y-m-d', strtotime("$year-$month-01"));
		$month_end =  date('Y-m-d', strtotime("$year-$month-$lastday"));

		$report = new WC_Report_Sales_By_Date();

		$_GET['start_date'] = $month_start;
		$_GET['end_date']   = $month_end;

		$report_data     = $report->calculate_current_range( 'custom' );

		if (is_wp_error($report_data)) {
			return;
		}

		$report_data     = $report->get_report_data();

		if ("$year$month" <> date("Ym")) {
			self::import_db('M',  $year.''.$month, $report_data);
		}

		EnergyPlus_Ajax::success(array('type' => 'import-month', 'date' => EnergyPlus_Helpers::strtotime("$month_start - 10 day", "Y-m-d"), 'det'=> '<div class="__A__x __A__'.EnergyPlus_Helpers::strtotime("$month_start - 10 day", "Y-m-d").'"><span class="dashicons dashicons-yes-alt text-success"></span><br>#' . EnergyPlus_Helpers::strtotime($date, "m"). ' imported successfully</div>'));
	} else if ('import-year' === EnergyPlus_Helpers::post('sub')) {

		include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-admin-report.php' );
		include_once( WC()->plugin_path() . '/includes/admin/reports/class-wc-report-sales-by-date.php' );

		$first = EnergyPlus_Helpers::strtotime(EnergyPlus_Helpers::post('first'), 'Y-m-01');
		$date = EnergyPlus_Helpers::strtotime(EnergyPlus_Helpers::post('range'), 'Y-m-d');

		$lastday = EnergyPlus_Helpers::strtotime(EnergyPlus_Helpers::post('range'), 't');
		$month = EnergyPlus_Helpers::strtotime(EnergyPlus_Helpers::post('range'), 'm');
		$year = EnergyPlus_Helpers::strtotime(EnergyPlus_Helpers::post('range'), 'Y');

		if (strtotime($date) < strtotime($first)) {
			EnergyPlus_Ajax::success(array('type' => 'import-ok', 'date' => "-1", 'det'=> '<div class="__A__x __A__-1"><span class="dashicons dashicons-yes-alt text-success"></span><br>Completed!</div>'));
			return;
		}

		$month_start =  date('Y-m-d', strtotime("$year-01-01"));
		$month_end =  date('Y-m-d', strtotime("$year-12-$lastday"));

		$report = new WC_Report_Sales_By_Date();

		$_GET['start_date'] = $month_start;
		$_GET['end_date']   = $month_end;

		$report_data     = $report->calculate_current_range( 'custom' );

		if (is_wp_error($report_data)) {
			return;
		}

		$report_data     = $report->get_report_data();

		if ($year <> date("Y")) {
			self::import_db('Y',  $year, $report_data);
		}

		EnergyPlus_Ajax::success(array('type' => 'import-year', 'date' => EnergyPlus_Helpers::strtotime("$date - 1 year", "Y-m-d"), 'det'=> '<div class="__A__x __A__'.EnergyPlus_Helpers::strtotime("$date - 1 year", "Y-m-d").'"><span class="dashicons dashicons-yes-alt text-success"></span><br>#' . EnergyPlus_Helpers::strtotime($date, "m"). ' imported successfully</div>'));
	}
	else {
		$_first_order_date = $wpdb->get_results(
			$wpdb->prepare("
			SELECT {$wpdb->prefix}posts.*
			FROM {$wpdb->prefix}posts
			WHERE post_type = %s ORDER BY post_date ASC LIMIT 1",
			'shop_order'
		), ARRAY_A );

		if (isset($_first_order_date[0])) {
			$first_order_date = EnergyPlus_Helpers::strtotime($_first_order_date[0]['post_date'], 'Y-m-d');
		}

		echo EnergyPlus_View::run('reports/import',  array('first_order_date' => $first_order_date));
	}
}

/**
* Rebuild energyplus_daily table
*
* @since  1.1.0
*/

public static function import_db($type, $day, $report_data) {
	global $wpdb;

	$result = array();

	$result['sales']          = floatval($report_data->total_sales);
	$result['orders']         = intval  ($report_data->total_orders);
	$result['customers']      = intval  ($report_data->total_customers);
	$result['net_sales']      = floatval($report_data->net_sales);
	$result['total_discount'] = floatval($report_data->total_coupons);
	$result['total_tax']      = floatval($report_data->total_tax);
	$result['total_shipping'] = floatval($report_data->total_shipping);
	$result['total_refunds']  = floatval($report_data->total_refunds);


	switch ($type) {
		case 'D':
		$goal = EnergyPlus::option('feature-goals-daily',0);
		break;
		case 'W':
		$goal = EnergyPlus::option('feature-goals-weekly',0);
		break;
		case 'M':
		$goal = EnergyPlus::option('feature-goals-monthly',0);
		break;
		case 'Y':
		$goal = EnergyPlus::option('feature-goals-yearly',0);
		break;
	}

	$data = array(
		'type'           => $type,
		'day'            => $day,
		'sales'          => $result['sales'],
		'orders'         => $result['orders'],
		'customers'      => intval($result['customers']),
		'goal'           => $goal,
		'net_sales'      => $result['net_sales'],
		'total_discount' => $result['total_discount'],
		'total_tax'      => $result['total_tax'],
		'total_shipping' => $result['total_shipping'],
		'total_refunds'  => $result['total_refunds'],
		'updated_at'     => current_time('mysql')
	);

	$check = $wpdb->get_var(
		$wpdb->prepare(" SELECT report_id FROM {$wpdb->prefix}energyplus_daily WHERE type = %s AND day = %s",
		$type, $day) );


		if ($check && intval($check)>0) {
			$wpdb->update( $wpdb->prefix."energyplus_daily",
			$data,
			array('report_id'=>$check) );
		} else {

			$wpdb->insert( $wpdb->prefix."energyplus_daily",
			$data,
			array('%s', '%s','%f', '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%s') );
		}
	}

	public static function manual() {
		global $wpdb;
		$type = str_replace(['daily', 'weekly','monthly'], ['D','W','M'], EnergyPlus_Helpers::post('t'));
		$day = intval(EnergyPlus_Helpers::post('day', '-1'));
		$val = intval(EnergyPlus_Helpers::post('val', '0'));

		$data['type'] = $type;
		$data['day'] = $day;
		$data['visitors'] = $val;
		$data['updated_at']     = current_time('mysql');

		$check = $wpdb->get_var(
			$wpdb->prepare(" SELECT report_id FROM {$wpdb->prefix}energyplus_daily WHERE type = %s AND day = %s",
			$type, $day) );

			if ($check && intval($check)>0) {
				$wpdb->update( $wpdb->prefix."energyplus_daily",
				$data,
				array('report_id'=>$check) );

			} else {

				$wpdb->insert( $wpdb->prefix."energyplus_daily",
				$data,
				array('%s', '%s','%d', '%s') );

			}
		}
	}

	?>
