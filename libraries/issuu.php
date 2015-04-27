<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Issuu {

	public function loadConfig($pubid)
        {
                $CI =& get_instance();                                                                                                                             
                $CI->load->helper('file');       
                $fcontent = read_file('/var/www/instances.txt');
                $fcontent = trim($fcontent);
                $data = explode("\n",$fcontent);
                $configs = array();
                foreach($data as $line) {
                        $d = explode(',',$line);
                        // each data line looks like this: trp,conskey,conssec.  So we want to put into an keyed array by pubid
                        $configs[$d[0]] = array(
                                'consKey'  => $d[1],
                                'consSec'  => $d[2],
                                'property' => trim($d[3])
                        );
                }
                return $configs[$pubid];
        }

	public function get_url_contents($url){
		// $ret = file_get_contents($url);
		$crl = curl_init();
		$timeout = 120;
		$user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/535.19 (KHTML, like Gecko) Chrome/18.0.1025.163 Safari/535.19';
		curl_setopt ($crl, CURLOPT_URL,$url);
		curl_setopt ($crl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($crl, CURLOPT_USERAGENT, $user_agent);
		curl_setopt ($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
		$ret = curl_exec($crl);
		curl_close($crl);
		return $ret;
	}

	public function titleCleanser($in)
	{
		$proc = preg_replace('/[^ \w]+/', '',$in);                                                                                                                             
                $proc = str_replace (' ','', $proc);                                                                                                                                     
                $out  = strtolower($proc);  
		return $out;   
	}

	public function outputFilePut($filename,$filedata)
        {
                $CI =& get_instance();
		$CI->load->helper('file');
                $CI->load->library('ftp');
		$dir = '/var/www/statics/';
                $fnp = $dir.$filename;
                $fnpts = $dir.'ts_'.$filename; // this is the timestamped version of the file to FTP
                $config['hostname'] = 'ftp.sx.atl.publicus.com';
                $config['username'] = 'bb.jgrady';
                $config['password'] = 'manassas703';
                $config['debug']    = FALSE;

                if (!file_exists($fnp) || ( md5(read_file($fnp)) != md5($filedata) ) )
                {
                        $timestamp = '<!-- cibuildtime: '.date(DATE_RSS).' -->';
                        // $timestamp = '<p style="color: #ececec;">updated: '.date(DATE_RSS).'</p>';
			write_file($fnp, $filedata, 'wt');
			write_file($fnpts, $filedata.$timestamp, 'wt');
			sleep(5);
                        $CI->ftp->connect($config);
                        $CI->ftp->upload($fnpts, '/Dev/inc/statics/fpfragments/'.'ts_'.$filename, 'auto', 0775);
                        $CI->ftp->upload($fnpts, '/Staging/inc/statics/fpfragments/'.'ts_'.$filename, 'auto', 0775);
                        $CI->ftp->upload($fnpts, '/Web/inc/statics/fpfragments/'.'ts_'.$filename, 'auto', 0775);
			
			// $CI->ftp->upload($fnp, '/Dev/inc/statics/fpfragments/'.$filename, 'auto', 0775);
                        // $CI->ftp->upload($fnp, '/Staging/inc/statics/fpfragments/'.$filename, 'auto', 0775);
                        // $CI->ftp->upload($fnp, '/Web/inc/statics/fpfragments/'.$filename, 'auto', 0775);
			$CI->ftp->close();
			sleep(5);
			// $CI->get_url_contents('http://bbedit.sx.atl.publicus.com/apps/pbcs.dll/frontpage?NoCache=1');
                }                                                                                                                                                                               
        }                                             
}
/* End of file issuu.php */
