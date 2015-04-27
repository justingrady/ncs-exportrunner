<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Ciparser {
	
	public function thumbnailGen($imageSrc)
        {
		$CI =& get_instance();
		// Two things:
                // 1. Hands out filename of thumbnail images, just string processing/
                // 2. Created thumbnail image, if one does not already exist (within else)
                // Wed May 23 16:25:23 PDT 2012 JTG
                $imageThumb = str_replace('.jpg','_thumb.jpg',$imageSrc); // _thumb name format is CodeIgniter default for image_lib
                // sample: /var/www/images/120523070707-4ccc2e729345440f97540c755d85a3ca_thumb.jpg              
		if (file_exists($imageThumb))
                {
			// Do nothing, we already have the thumbnail image generated
                        // Built this way, so we know the logic of this function
                        // Wed May 23 16:14:02 PDT 2012 JTG
                }
                else
                {
			$config['image_library'] = 'gd2';
                        $config['source_image'] = $imageSrc;
                        $config['create_thumb'] = TRUE;
                        $config['maintain_ratio'] = TRUE;
                        $config['width']         = 150;
                        $config['height']       = 169;
                        $CI->load->library('image_lib');

                        $CI->image_lib->clear();
                        $CI->image_lib->initialize($config);
                        if ( ! $CI->image_lib->resize())
                        {
                                $CI->upload_error = $CI->image_lib->display_errors();
                                $CI->image_lib->clear();
                                return false;
                        }
                }
                $imageThumb = ltrim($imageThumb,'/var/www');
                return $imageThumb;
        }

	public function get_url_contents($url){
		// $ret = file_get_contents($url);
		$crl = curl_init();
		$timeout = 3600;
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

	public function imagePull($source,$mediaTitle,$ArTitle,$pubdate,$prefix=NULL)
	{
                $CI =& get_instance();
		$CI->load->helper('file');
                $CI->load->helper('url');
                $CI->load->library('ciparser');
                // I know this whole next section is ugly. JTG 1655 05032012
                if($mediaTitle != NULL)
                {
                        $mediaTitle = trim($mediaTitle,'.jpg'); // prepend as DIT ID, then strip .jpg from DTI image ID.       
                } else {
                        $mediaTitle = NULL;
                }
		
		$ArTitle    = $CI->ciparser->titleCleanser($ArTitle);
		$mediaTitle = $CI->ciparser->titleCleanser($mediaTitle);
                $pubdate = strtotime($pubdate);
                $pubdate = date('mdY',$pubdate);
		$prefix  = ($prefix != NULL ? $prefix.'-' : NULL);
		if($ArTitle == $mediaTitle)
		{
                	$filestring = $prefix.$ArTitle.'-'.$pubdate.'.jpg';
		}
		else
		{
                	$filestring = $prefix.$mediaTitle.'-'.$ArTitle.'-'.$pubdate.'.jpg';
		}	

                if($source == NULL)
                {
                        return NULL;
                }
                else
                {
                        $path           =       '/var/www/images/';
                        $filename       =       $path.$filestring;
                        $imageURI       =       base_url().'images/'.$filestring;

                        if (!file_exists($filename))
                        {
                                $source = trim($source);
				// $fileguts = $CI->ciparser->get_url_contents($source);
                                write_file($filename, file_get_contents($source), 'c');                                                                                                        
                                // write_file($filename, $fileguts, 'c');                                                                                                        
                                // print_r('image source: '.$source."<br>\n");                                                                                                                 
                        }                                                                                                                                                                      
                        // $this->ciparser->thumbnailGen($filename);                                                                                                                           
                        $output = array(                                                                                                                                                       
                                'uri'           =>      $imageURI,                                                                                                                             
                                'orig'		=>	$imageURI,
				'thumb'         =>      base_url().$CI->ciparser->thumbnailGen($filename),                                                                                   
                                'timestamp'     =>      filemtime($filename) // want 'last updated' time of file                                                                               
                        );
                        return $output;                                                                                                                                                        
                }                                                                                                                                                                              
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

	public function dtiExpFilePut($filename,$filedata,$filedate)
        {
                $CI =& get_instance();
		$CI->load->helper('file');
                $CI->load->library('ftp');
		$dir = '/var/www/exports/dti/xml/';
		// $dir .= date('Y',$filedate).'/';
                $fnp = $dir.$filename;
                $fnpts = $dir.'ts_'.$filename; // this is the timestamped version of the file to FTP
                // $config['hostname'] = 'tbb-ftp.tbb.us1.dti';
		$config['hostname'] = '10.100.77.40';
                $config['username'] = 'justin.grady';
                // $config['password'] = 'dtidw-37!-_UVC9jRXrC_7Yn:k!:gu';
                $config['password'] = 'dtidw-53f6VU8Na4sJ4795758M';
                $config['debug']    = FALSE;

		if($filename != '19691231__.xml')
		{
			if (!file_exists($fnp) || ( md5(read_file($fnp)) != md5($filedata) ) )
			{
				$timestamp = '<!-- cibuildtime: '.date(DATE_RSS).' -->';
				// $timestamp = '<p style="color: #ececec;">updated: '.date(DATE_RSS).'</p>';
				write_file($fnp, $filedata, 'wt');
				write_file($fnpts, $filedata.$timestamp, 'wt');
				// sleep(1);
				$CI->ftp->connect($config);
				$CI->ftp->upload($fnpts, '/Interfaces/Story Import/FileIn/'.'ts_'.$filename, 'auto', 0775);
				echo '<div class="xml"><h5>XML</h5><p>uploaded -> '.$fnpts.'</p><br style="clear: both;"/></div>';
                	}
		} 
        }

	public function dtiImgFilePut($filename)
        {
                $CI =& get_instance();
		$CI->load->helper('file');
                $CI->load->library('ftp');
		$dir = '/var/www/exports/dti/images/';
                $imgfile = $dir.$filename;
		$config['hostname'] = '10.100.77.40';
                $config['username'] = 'justin.grady';
                // $config['password'] = 'dtidw-37!-_UVC9jRXrC_7Yn:k!:gu';
                $config['password'] = 'dtidw-53f6VU8Na4sJ4795758M';
                $config['debug']    = FALSE;
		// $uploadpath = '/Interfaces/News Media Import/FileIn/';
		// $uploadpath = '/Autoload News Media/';
		$uploadpath = '/Refresh News Media/';

		$CI->ftp->connect($config);
		$CI->ftp->upload($imgfile, $uploadpath.$filename, 'auto', 0775);
		echo '<div class="img"><h5>IMG</h5><p>uploaded -> '.$uploadpath.$filename.'</p><br style="clear: both;"/></div>';
		sleep(1);
        }

	public function dTimagePull($source,$object,$ArTitle,$pubdate,$prefix=NULL)
	{
                $CI =& get_instance();
		$CI->load->helper('file');
                $CI->load->helper('url');
                $CI->load->library('ciparser');
                // I know this whole next section is ugly. JTG 1655 05032012
                $mediaTitle = $object['saxo:mediatitle'];
		if($mediaTitle != NULL)
                {
                        $mediaTitle = trim($mediaTitle,'.jpg'); // prepend as DIT ID, then strip .jpg from DTI image ID.       
                } else {
                        $mediaTitle = NULL;
                }
		
		$ArTitle    = $CI->ciparser->titleCleanser($ArTitle);
		$mediaTitle = $CI->ciparser->titleCleanser($mediaTitle);
                $pubdate = strtotime($pubdate);
                $pubdate = date('mdY',$pubdate);
		$prefix  = ($prefix != NULL ? $prefix.'-' : NULL);
		
		$filestring = $prefix.$ArTitle.'-'.$pubdate.'.jpg';
		$xmlfilestring = $prefix.$ArTitle.'-'.$pubdate;
		// $filestring = $prefix.$ArTitle.'-'.$pubdate;

		/*
		if($ArTitle == $mediaTitle)
		{
                	$filestring = $prefix.$ArTitle.'-'.$pubdate.'.jpg';
		}
		else
		{
                	$filestring = $prefix.$mediaTitle.'-'.$ArTitle.'-'.$pubdate.'.jpg';
		}	
		*/

                if($source == NULL)
                {
                        return NULL;
                }
                else
                {
                        $path           =       '/var/www/exports/dti/images/';
                        $filename       =       $path.$filestring;
                        $imageURI       =       base_url().'exports/dti/images/'.$filestring;

                        if (!file_exists($filename))
                        {
                                $source = trim($source);
                                write_file($filename, file_get_contents($source), 'c');                                                                                                        
                                // print_r('image source: '.$source."<br>\n");                                                                                                                 
                        }                                                                                                                                                                      
                        // $this->ciparser->thumbnailGen($filename);                                                                                                                           
                        $output = array( 
                                'uri'           	=>      $imageURI,
				'imgfilename'		=>	$filestring,
				'xmlimgfilestring'	=>	$xmlfilestring,
                                'orig'			=>	$imageURI,
				// 'thumb'         =>      base_url().$CI->ciparser->thumbnailGen($filename),
                                'timestamp'     =>      filemtime($filename) // want 'last updated' time of file 
                        );

			$output = array_merge($object,$output);
                        $CI->ciparser->dtiImgFilePut($output['imgfilename']);
			// print_r($output);
			return $output;                                                                                                                                                        
                }                                                                                                                                                                              
        }                                                    
	
	public function categorytranslate($in)
	{
		$CI =& get_instance();
		$CI->load->helper('file');
		$fcontents = read_file('/var/www/exports/dti/lookuptable.txt');
		// print_r($fcontents);
		$lines = array();
		$lines = explode("\n",$fcontents);
		$lookupkey = array();
		foreach ($lines as $line)
		{
			if(strstr($line,'##') == TRUE)
			{
				// do nothing	
			}
			else
			{
				$vars = array();
                                $vars = explode("\t",$line);
				if(is_numeric($vars[0]))  // we only want the saxo profiled nunbered lines
				{
					$vars[0] = (int)$vars[0];
					$lookupkey[$vars[0]] = array(
						'saxoprofile'		=>	$vars[0],
						'dtcategoryname'	=>	$vars[1],
						'dtcategoryid'		=>	$vars[2],
						'dtsubcategoryname'	=>	($vars[3] != 'None' ? $vars[3] : NULL), // if subcat is None, set to null
						'dtsubcategoryid'	=>	$vars[4],
					);
				}
				unset($vars);	
			}
		}
		$in = trim($in);
		return $lookupkey[$in];
	}
}
/* End of file ciparser.php */
