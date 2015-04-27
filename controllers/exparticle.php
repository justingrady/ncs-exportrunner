<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
* Added preg_replace to remove iframes from output within stripHTMLtags function. We were getting partial iframes in blobs
* JTG Tue Sep 17 07:58:37 PDT 2013
*/

class Exparticle extends CI_Controller {

	function articlelist($date,$count=NULL)
        {
		$date = ($date == NULL) ? date('Ymd',time()) : date('Ymd',strtotime($date));
		$this->load->library('ciparser');	
		$url = 'http://bbweb.sx.atl.publicus.com/apps/pbcs.dll/section?Category=rssfc&Mime=xml&Omniture=0&feed=1&date='.$date;
		$data['items'] = $this->_getXMLvars($url,'item',$count);
		$this->output->set_content_type("text/html;charset=UTF-8");
		$this->output->set_header("Cache-Control: post-check=0, pre-check=0");
		$this->load->view('export/header');
		$this->articleFileWrite($data);
		$this->load->view('export/footer');
		
		// $this->load->view('export/render', $data);
		// sleep(5);
		// $filedata = $this->output->get_output();
		// $filename = 'fpblock-'.$profile.'.html';
		// $this->ciparser->outputFilePut($filename,$filedata); 
	}

	private function articleFileWrite($data)
	{
		foreach ($data as $items)
		{
			foreach ($items as $item)
			{
				// get output of view into $filedata string
				$filedata = $this->load->view('export/single', $item, true);
				// make our filename
				$ftitle = array();
				$ftitle['title'] = $this->ciparser->titleCleanser($item['title']);
				$ftitle['pubdate'] = date('Ymd', $item['unixdate']);
				$category = '';
				$category .= isset($item['categories']['dtcategoryname']) ? $item['categories']['dtcategoryname'] : NULL;
				$category .= '-';
				$category .= isset($item['categories']['dtsubcategoryname']) ? $item['categories']['dtsubcategoryname'] : NULL;
				$ftitle['category'] = $this->ciparser->titleCleanser($category);
				$filename = $ftitle['pubdate'].'_'.$ftitle['title'].'_'.$ftitle['category'].'.xml';
                		// do stuff with files
				// print_r($filedata);
				$this->ciparser->dtiExpFilePut($filename,$filedata,$item['unixdate']); 
				// $this->ciparser->dtiImgFilePut($item['mediaitem']['filename']); 
				unset($output);
				unset($filedata);
				unset($item);
			}	
		}
		// $this->load->view('export/single', $data);
	}

	public function imageSourcer($row,$imageobject)
	{
		$count = 0;
		foreach($imageobject as $object)
		{
			$object['saxo:mediaurl'] = str_replace('www.bendbulletin.com','bbweb.sx.atl.publicus.com',$object['saxo:mediaurl']);	
			if($object['saxo:mediatitle'] == NULL)
			{
				$imgSource      =       $object['saxo:mediaurl'];
			}
			elseif($object['saxo:mediatitle'] != NULL)
			{
				$icpsfile = '/mnt/news/icps/publicus_uploaded_archive/'.date('Ymd').'/home/dti/filtered/images/'.trim($object['saxo:mediatitle']);
				if(file_exists($icpsfile))
				{
					$imgSource = $icpsfile;
					// $imgSource = $object['saxo:mediaurl'];
				}
				else
				{
					$imgSource = $object['saxo:mediaurl'];
				}
			}
			// $images = $this->_filePull($imgSource,$row['media:title'],$this->_cleanser($row['title']),$row['pubDate']);
			$images[$count] = $this->ciparser->dTimagePull($imgSource,$object,$this->_cleanser($row['title']).'-'.$count,$row['pubDate'],'saxo'); // that bb is the image prefix 
			unset($imgSource);
			$count++;
		}
		return $images;
	}

	function nitfExtractor($urls)
	{
		$urlDomain = 'http://bbweb.sx.atl.publicus.com';
		$urlIncoming = $urlDomain.$urls['nitf']['URL'];
		echo '177--'.$urlIncoming.'--177';
		$xmlRaw = $this->ciparser->get_url_contents($urlIncoming);
		$this->load->library('simplexml');  // I wish I would have done objects instead, but it f'in works, want this project done!
                $xmlData = array();
		$xmlData = $this->simplexml->xml_parse($xmlRaw);
		$byline = NULL;
		$byline .= ($xmlData['body']['body.head']['byline']['person'] != NULL ? $xmlData['body']['body.head']['byline']['person'] : NULL);
		$byline .= ($xmlData['body']['body.head']['byline']['byttl'] != NULL ? ' / '.$xmlData['body']['body.head']['byline']['byttl'] : NULL);
		$sidebar = $xmlData['body']['body.content']['block'];
		$output = array(
			'byline'  =>  $byline,
			'sidebar' =>  array(
				'headline' => $this->_descriptionPrep($xmlData['body']['body.content']['block']['hl2'],'head'),
				'text'     => $this->_descriptionPrep($xmlData['body']['body.content']['block']['note'])
			)
		);
		// $output['toolboxMarkup'] = '<div id="toolbox" class="toolbox">';
		$output['toolboxMarkup'] = '<div class="col-md-6 toolbox2">';
		$output['toolboxMarkup'] .= $output['sidebar']['headline'];
		$output['toolboxMarkup'] .= $output['sidebar']['text'];
		$output['toolboxMarkup'] .= '</div>';
		return $output;	
	}
	
	function _getXMLvars($urlIncoming,$type,$count=NULL)
        {
		error_reporting(E_ALL ^ (E_NOTICE | E_WARNING)); // HAD to put this in, as keys are defined by incoming XML, but wanted to reference keys in k/v foreach below
		$this->load->library('ciparser');
		$this->load->helper('array');
		$this->load->helper('text');
		$result =       NULL;
                $lcount =       0;
		$xmlRaw = $this->ciparser->get_url_contents($urlIncoming);
		$this->load->library('simplexml');  // I wish I would have done objects instead, but it f'in works, want this project done!
		$xmlData 	= 	$this->simplexml->xml_parse($xmlRaw);
	
		switch ($type)
		{
			case "header":
				unset($xmlData['channel']['item']);
				$result	=	$xmlData['channel'];
				// $result	=	array_walk_recursive($result);			
				break;
			break;
			case "item":
				// unset($result);
				unset($xmlData['channel']['header']);
				$result	=	NULL;
				$result =	array();
				
				if(isset($xmlData['channel']['item']['category'])) 
				{
					$tmp	=	$xmlData['channel']['item'];
					unset($xmlData['channel']['item']);
					$xmlData['channel']['item'] = array();
					$xmlData['channel']['item'][0] = $tmp;
					unset($tmp);
				}

				foreach($xmlData['channel']['item'] as $row)
                		{
					$dtiimgcount = 0;
					if($row['saxo:vars']['saxo:mediagroup'] != NULL)
					{	
						// print_r($row['saxo:vars']['saxo:mediagroup']);
						$dtiimgobject = array();
						foreach($row['saxo:vars']['saxo:mediagroup']['saxo:mediaitem'] as $saxoimgobject)
						{
							// print_r($saxoimgobject);
							// if($saxoimgobject['saxo:mediatitle'] != 'filler')
							$mediatitle = $saxoimgobject['saxo:mediatitle'];
							if (strpos($mediatitle, 'filler') !== FALSE)
								// NO WANT!
								$imgAllow = 0;
							elseif(strpos($mediatitle, 'T_') !== FALSE)
								// NO WANT
								$imgAllow = 0;
							else{
								// DO WANT!
								$imgAllow = 1;
							}

							if($imgAllow == 1)
							{
								$dtiimgobject[$dtiimgcount] = $saxoimgobject;
								$dtiimgobject[$dtiimgcount]['status'] = 'Web Ready'; // DTI wants this
								$dtiimgobject[$dtiimgcount]['caption'] = $this->_cleanser($saxoimgobject['saxo:mediacaption']); // DTI wants this
								$dtiimgobject[$dtiimgcount]['byline'] = $this->_cleanser($saxoimgobject['saxo:mediabyline']); // DTI wants this
								$dtiimgobject[$dtiimgcount]['source'] = 'sx-'.date(DATE_RSS);
								$dtiimgobject[$dtiimgcount]['desk'] = 'zArchive';
								$dtiimgobject[$dtiimgcount]['prioname'] = 'Web Ready';
								$dtiimgobject[$dtiimgcount]['statusname'] = '4.Worked';
								$dtiimgcount++;
							}
							unset($imgAllow);
						}
					}
					
					$images = $this->imageSourcer($row,$dtiimgobject);
					unset($dtiimgobject);
					$embeds = $this->embedParser($row['saxo:vars']['saxo:embed']);
					$paywall = $this->payWallChecker($row['link']);
					$legacyUrls = $this->urlmaker($row['link'],$row['saxo:vars']['saxo:profilenum']);
					$nitfData = $this->nitfExtractor($legacyUrls);
	
					$guid = $row['guid']['@content'];
					// by usind $guid as the array key, it prevents duplicates, as the key is already set, first index of key is used
					
					$result[$guid]    =       array(
						'dtiStoryName'		=>	$this->idmaker($row['link'],'dtid',$this->ciparser->titleCleanser($row['title'])),
						'categories'		=>	$this->ciparser->categorytranslate($row['saxo:vars']['saxo:profilenum']),
						'saxoprofile' 		=>	$row['saxo:vars']['saxo:profilenum'],
						'title'                 =>      $this->_cleanser($row['title']),
						'link'                  =>      $row['link'],
						// 'priorityname'		=>	'1. Web Ready',
						'priorityname'		=>	'Web Processed',
						'paywall'		=>	$paywall['bool'],
						'keywords'		=>	$this->keywordbuilder($row['media:keywords']),
						'mediaitems'		=>	$images,
						'author'		=>	$row['dc:creator'],
						'description'           =>      $this->_descriptionPrep($row['description']),
                                		'iddisqus'		=>	$this->idmaker($row['link'],'disqus'),
						'pubdate'               =>      $row['pubDate'],
						'unixdate'		=>	strtotime($row['pubDate']),
						'deskname'		=>	'zArchive',
						'origin'		=>	'sx-'.date(DATE_RSS),
						'statusname'		=>	'8. Ready',
						'userdefdate1'		=>	strtotime($row['pubDate']), // is Custom --> Pud Date in lightning
						'userdefdate2'		=>	'', // yep, nothing for now
						'userdefint1'		=>	$paywall['int'], // lighting paywall value
						'userdefint2'		=>	'',
						'userdefmoney1'		=>	'00', // yeah, want a double zero string
						'userdefmoney2'		=>	NULL,
						'userdefstr1'		=>	'bendbulletin',
						'userdefstr2'		=>	'saxo-'.$this->idmaker($row['link'],'aid'), // OriginalStoryID
						'userdefstr3'		=>	NULL, // unused
						'userdefstr4'		=>	NULL, // SectionLayout
						'userdefstr5'		=>	NULL, // unused
						'userdefstr6'		=>	NULL, // unused
						'storyruninfo'		=>	array(
							'rundate'		=>	strtotime($row['pubDate']),
							'legacyurl'		=>	$row['link']
										),
						'legacyurllist'		=>	array_slice($legacyUrls, 0, 4), // remove last url, which is nitf
						'storyelementsbyname'	=>	array(
							'kicker'		=>	$this->_cleanser($row['saxo:vars']['saxo:kicker']),
							'printheadline'		=>	$this->_cleanser($row['saxo:vars']['saxo:printhead']),
							'printsubhead'		=>	$this->_cleanser($row['saxo:vars']['saxo:printsubhead']),
							// 'printbyline'		=>	$row['saxo:vars']['saxo:byline'],
							'printbyline'		=>	$nitfData['byline'],
							'printtext'		=>	$this->_descriptionPrep($row['saxo:vars']['saxo:bodytext']),
							'webheadline'		=>	($this->_cleanser($row['saxo:vars']['saxo:webhead']) != '' ? $this->_cleanser($row['saxo:vars']['saxo:webhead']) : $this->_cleanser($row['saxo:vars']['saxo:printhead']) ),
							// 'webheadline'		=>	$this->_cleanser($row['saxo:vars']['saxo:webhead']),
							'websubhead'		=>	($this->_cleanser($row['saxo:vars']['saxo:websubhead']) != '' ? $this->_cleanser($row['saxo:vars']['saxo:websubhead']) : $this->_cleanser($row['saxo:vars']['saxo:printsubhead']) ),
							'webbyline'		=>	$nitfData['byline'],
							'webtext'		=>	$this->_descriptionPrep($row['saxo:vars']['saxo:bodytext']),
							'websummary'		=>	$this->summaryMaker($row['saxo:vars']['saxo:bodytext'], 65),
							'toolbox'		=>	$nitfData['toolboxMarkup'],
							'toolboxflag'		=>	($nitfData['sidebar']['text'] != '' ? TRUE : FALSE),
							// 'toolbox'		=>	$nitfData['// sidebar'],
							'video'			=>	$embeds['youtube'],
							'htmltext'		=>	$embeds['otherembed'],
							'alternateurl'		=>	$row['link']
										)	
                        		);
                        		
					unset($images);
					unset($paywall);
					unset($legacyUrls);
					unset($nitfData);
					unset($guid);

					// $result[$lcount] = array_filter($result[$lcount]); // if there are empty keys, they are stripped out of array.
                        		$result[$guid] = array_filter($result[$guid]); // if there are empty keys, they are stripped out of array.
					$lcount++;
                        		if($lcount == $count) : break;
                        		endif;
				}
                        	break;
		}
		// print_r($result);
		return $result;
        }

	private function keywordbuilder($in)
	{
		$wordstring = str_replace("\n",'',$in);
		$wordstring = str_replace('&', '&amp;', $wordstring);
		// print_r($wordstring);
		$tempwords = array();
		$tempwords = explode(',', $wordstring);
		$words = array();
		$count = 0;

		$swaps = array(
			'wescom'		 => 'wescomwire',
			'Dontmiss'		 => NULL,
			'has thumbnail'		 => NULL,
			'front page A1'		 => NULL,
			'blobprio-business' 	 => 'Business',
			'blobprio-commlife' 	 => 'Community Life',
			'blobprio-drinks'   	 => 'Drinks',
			'blobprio-entertainment' => 'Entertainment',
			'blobprio-family'	 => 'Family',
			'blobprio-health'	 => 'Health',
			'blobprio-homegarden'	 => 'Home and Garden',
			'blobprio-local'	 => 'Local',
			'blobprio-music'	 => 'Music',
			'blobprio-nationworld'	 => 'Nation and World',
			'blobprio-opinion'	 => 'Opinion',
			'blobprio-outdoors'	 => 'Outdoors',
			'blobprio-restaurants'	 => 'Restaurants',
			'blogprio-sports'	 => 'Sports'	
		);

		foreach($tempwords as $tempword)
		{
			$tempword = trim($tempword);
			$tempword = strtr($tempword,$swaps);
			if($tempword != '')
			{
				$words[$count] = $tempword;
				$count++;
			} 	
		}
		return $words;
	}

	private function urlmaker($link,$profilenum)
	{
		$keys = array('domain','type','artdate','artcategory','artid');
		$sturl = ltrim($link,'http://');
                $sturl = rtrim($sturl,'/');
                $vals =  explode('/',$sturl);
                $segments = array_combine($keys,$vals);
                // we want the 4 digit year only for disqus
                $segments['pubyear']= date('Y',strtotime($segments['artdate']));
		$segments['profilenum'] = ($profilenum != '' ? $profilenum : NULL);
		$segments['crap'] = 'apps/pbcs.dll/article?AID=';
		$segments['article'] = 'article';
		$segments['shortdomain'] = 'bendbulletin.com';
		$segmants['localhost'] = 'localhost';

		$urls = array(
			// 'canonical' => $link,
			'canonical2'  => 'http://'.$segments['domain'].'/'.$segments['article'].'/'.$segments['artdate'].'/'.$segments['artcategory'].'/'.$segments['artid'].'/',
			'canonical3'  => 'http://'.$segments['shortdomain'].'/'.$segments['article'].'/'.$segments['artdate'].'/'.$segments['artcategory'].'/'.$segments['artid'].'/',
			// 'canonical4'  => $segments['localhost'].'/'.$segments['article'].'/'.$segments['artdate'].'/'.$segments['artcategory'].'/'.$segments['artid'].'/',
			// 'oldstyle'  => '/'.$segments['crap'].'/'.$segments['artdate'].'/'.$segments['artcategory'].'/'.$segments['artid'].'/'.$segments['profilenum'],
			'oldstyle'  => 'http://'.$segments['domain'].'/'.$segments['crap'].'/'.$segments['artdate'].'/'.$segments['artcategory'].'/'.$segments['artid'].'/'.$segments['profilenum'],
			'oldstyle2'  => 'http://'.$segments['shortdomain'].'/'.$segments['crap'].'/'.$segments['artdate'].'/'.$segments['artcategory'].'/'.$segments['artid'].'/'.$segments['profilenum'],
			// 'oldstyle4'  => $segments['localhost'].'/'.$segments['crap'].'/'.$segments['artdate'].'/'.$segments['artcategory'].'/'.$segments['artid'].'/'.$segments['profilenum'],
			'nitf'      => '/apps/pbcs.dll/article?AID=/'.$segments['artdate'].'/'.$segments['artcategory'].'/'.$segments['artid'].'&template=NITF&Mime=xml&Omniture=0'
		);

		$urldata = array();
		$i = 0;
		foreach($urls as $key => $val)
		{
			$urldata[$key] = array (
				'URL'		  =>	$val,
				'PublicationName' =>	'Shared',
				'SectionPath'     =>	'/News/',
				'PageLayout'      =>	'default',
				'FullTemplate'    =>	'FullStory'
			);
			$i++;	
		}	
		return($urldata);
	}

	private function embedParser($in)
	{
		$outputs = array();

		$document = new DOMDocument();
		$document->loadHTML($in);
		$lst = $document->getElementsByTagName('iframe');

		for ($i=0; $i < $lst->length; $i++) {
			$iframe = $lst->item($i);
			$outputs[$i] = $iframe->attributes->getNamedItem('src')->value;
		}
		// of there's more than one iframe in the markup, we only want the first one
		
		if(strpos($outputs[0], 'youtube') !== FALSE)
		{
			// we want only the embed url string from youtube
			$render['youtube'] = $outputs[0];
			$render['otherembed'] = NULL;
		}
		else
		{
			// no youtube here, but we got the other embed
			$render['youtube'] = NULL;
			$render['otherembed'] = $in;
		}
		return $render;
	}

	private function idmaker($link,$type,$title=NULL) 
	{
		// throw canonical url into an array
		$keys = array('domain','type','artdate','artcategory','artid');
		$sturl = ltrim($link,'http://');
		$sturl = rtrim($sturl,'/');
		$vals =  explode('/',$sturl);
		$segments = array_combine($keys,$vals);
		// we want the 4 digit year only for disqus
		$segments['pubyear']= date('Y',strtotime($segments['artdate']));

		switch ($type) {
    			case 'disqus':
				$output = $segments['pubyear'].'_'.$segments['artid'];
				break;
			case 'aid':
				$output = $segments['artdate'].'_'.$segments['artcategory'].'_'.$segments['artid'];
				break;
			case 'dtid':
				// $output = 'saxo_'.$title.'_'.$segments['artdate'];
				$output = 'sx-'.$title.'_'.$segments['artdate'];
				break;
		}
		return $output;
	}	

	private function payWallChecker($url)
	{
		// http://www.bendbulletin.com/article/20121010/NEWS01/210100393/1001
		$url = ltrim($url,'http://www.bendbulletin.com/');
		$t = explode('/',$url);
		$cat = (string) $t[2]; // get the article category, such as NEWS01

		// FREE categories to check
		$freecats = array(
			'NEWS0107',
			'BIZ0102',
			'SPORTS05',
			'NEWS05',
			'WEA01',
			'MV',
			'SPORTS0411',
			'AE02',
			'CORREX',
			'TRAVEL',
			'VOTE01',
			'COM',
			'COM01',
			'COM02',
			'COM03',
			'COM04',
			'COM05',
			'COM06',
			'COM0601',
			'COM07',
			'COM0701',
			'COM0702',
			'COM0703',
			'COM0704',
			'COM0705',
			'COM0706',
			'COM0707',
			'COM0708',
			'COM0709',
			'FREE'
		);

		$output = array(
			'bool'	=> 	(in_array($cat,$freecats,TRUE) ? (bool) FALSE : (bool) TRUE),
			// TRUE = free article
			// FALSE = paywalled article
			'int'	=>	(in_array($cat,$freecats,TRUE) ? (string) '00' : (string) '01')
			// 00 = free article
			// 01 = paywalled article
			// using double digit is such a hack job, see view as to why
		);
		return $output;	
	}

	function _descriptionPrep($string,$class=NULL)
	{
		$output	=	NULL;
		$lcount	=	0;
		
		if($string != '')
		{	
			// CDATA tags are put in the view file 'render', want to keep data as clean as possible for other outputs, JTG 4/13/11
			// $string	=	ltrim($string,'<![CDATA[');
			// $string	=	rtrim($string,']]');
			$string =	explode('<br>',$string);

			foreach($string as $paragraph)
			{
				$output .=      ($class != NULL ? '<p class="'.$class.'">' : '<p>');
				$output	.=	trim($this->_cleanser($paragraph)); // just in case there whitespace crap, we trim it
				$output .=	'</p>';
				$lcount++;
			}
			// $output	=	ltrim($output,'<br/><br/>'); // don't want the 1st two line breaks in description, want everywhere else.
			// $output         =       $this->stripHTMLtags($output);
			$output		=	trim($output,' '); // trim spaces start/end of string
		}
		else
		{
			$output = NULL;
		}
		return $output;
	}

	function _cleanser($string)
	{
		
		if($string != '')
		{

			/// a good lookup table for UTF-8 entities
			// http://webdesign.maratz.com/lab/utf_table/
			$string         =       trim($string);
			$swaps          =       array(
				'&lsquo;'       		=>      '&#8216;',
				'&rsquo;'       		=>      '&#8217;',
				'&ldquo;'       		=>      '&#8220;',
				'&rdquo;'       		=>      '&#8221;',
				'AT&T'          		=>      'AT&#38;T',
				'R&D'           		=>      '&#38;',
				' &amp;amp; '			=>	' &#38; ',
				'&frac12;'      		=>      ' 1/2',
				'½'				=>	' 1/2',
				"\xef\xbb\xbf"  		=>      '',
				'<br>'				=>	'<br/><br/>',
				']]></description>'		=>	'</p>]]></description>',
				'<b> </b>'			=>	'',
				'<b>'				=>	'<strong>',
				'</b>'				=>	'</strong>',
				' & '				=>	' &#38; ',
				'<em style="bold" class="hl2_chapterhead">'	=>	'<em>',
				"\n"				=>	'',
				'&#x2019;'			=>	'&#8217;',
				'&#x201c;'			=>	'&#8220;',
				'"'				=>	'&#8221;',
				'‘'				=>	'&#8216;',
				'’'				=>	'&#8217;',
				'“'				=>	'&#8220;',
				'”'				=>	'&#8221;',
				"'"				=>	'&#39;',
				'• '				=>	'',
				' s '				=>	'&#44;s '
			);
			$output         =       strtr($string, $swaps);
			$output		=	stripslashes($output);
			$output		=	$this->stripHTMLtags($output);
			// $output		=	strip_tags($output);
			// $output         =       htmlspecialchars($output);
		}
		else
		{
			$output = NULL;
		}

		return $output;
	}

	private function summaryMaker($str,$count)
	{
		$this->load->helper('text');
		$t = $str;
		$t = $this->_cleanser($t);
		$t = strip_tags($t);
		$t = str_replace('.', '. ',$t);
		$t = word_limiter($t,$count);
		return $t;
	}

	private function stripHTMLtags($str)
	{
		$t	=	preg_replace("/<[^<|>]+? >/", '', htmlspecialchars_decode($str));
		$t	=	preg_replace('/<iframe.*?\/iframe>/i','', $t); // strip iframes from output, Tue Sep 17 07:52:43 PDT 2013
		return $t;
	}
	
}
