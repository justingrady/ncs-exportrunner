<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Expscheduler extends CI_Controller {

	function run($dateStart,$dateEnd) // format: YYYYMMDD / 20131203 --> December 3rd, 2013 laymans terms
        {
		$this->load->library('ciparser');
		$this->output->set_content_type("text/html;charset=UTF-8");
		$this->output->set_header("Cache-Control: post-check=0, pre-check=0");
		$range = $this->dateBuilder($dateStart,$dateEnd);
		$urls = $this->urlConstructor($range);
		$go = $this->runUrls($urls,$range);
		print_r($go);
	}

	public function dateBuilder($start,$end)
	{
		// reformat dates a bit because of this
		// http://stackoverflow.com/questions/9471710/php-date-interval-date-period-not-behaving-as-expected
		$pstart = date('m/d/y',strtotime($start)) . ' 00:00:00';	
		$pend   = date('m/d/y',strtotime($end)) . ' 23:59:59';	
	
		$points = array(
			'start'	=> new DateTime($pstart),
			'end'   => new DateTime($pend),
			'interval' => DateInterval::createFromDateString('1 day')
		);
	
		$period = new DatePeriod($points['start'], $points['interval'], $points['end']);

		$dates = array();
		$i = 0;

		foreach ($period as $dt)
		{
			$dates[$i] = $dt->format('Ymd');
			$i++;
		}
		// print_r($dates);
		return $dates;	
	}

	public function runUrls($urls,$dates)
	{
		$this->load->library('ciparser');
		$this->load->helper('file');
		$dateStart = strtotime(array_shift($dates));
		$dateEnd = strtotime(array_pop($dates));
		$dateFormat = 'Ymd h:i:s'; 
		$filename = date('Ymd',$dateStart).'->'.date('Ymd',$dateEnd).'.txt';
		$fnp = '/var/www/exports/dti/logs/'.$filename;
		$output = array();
		// print_r($urls);
		foreach($urls as $key => $url)
		{
			$prepend = date($dateFormat)."\t".$key."\t->\t";
			$temp = $this->ciparser->get_url_contents($url);
			$done = "\t->\t".date($dateFormat)."\n";
			if($temp != NULL)
			{
				$contents = $prepend.$temp.$done;
				write_file($fnp, $contents, 'a+');
				$output[$key] = $contents;
				$contents = NULL;
			}
		}
		return $output;
	}

	public function urlConstructor($dates)
	{
		$this->load->helper('url');		
		$cgi = array(
			'base'	     => base_url(),
			'controller' => 'exparticle',
			'function'   => 'articlelist'
		);
		$urls = array();
	
		foreach($dates as $date)
		{
			$urls[$date] = $cgi['base'].$cgi['controller'].'/'.$cgi['function'].'/'.$date;
		}
		return $urls;
	}
}
