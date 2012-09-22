<?php defined('SYSPATH') or die('No direct script access.');

/*
 *	Compiler css, js and less files
 *	---------------------------------------
 *	KDM CMS based on the Kohana framework
 *	by Dmitry Momot	(#DimkOf)
 *	---------------------------------------
 *	Project support:	dmitry@dmstudio.pro
 *	Visit:				http://dmstudio.pro
 *						http://dimkof.com
 *						http://dimkof.ru
 */
 
class Minify_Core {

	protected $source_link;
	protected $type;
	protected $config;
	

	public static function factory($type, $source_link)
	{
		if($source_link == NULL OR $type == NULL)
		{
			throw new HTTP_Exception_500('Parameters are not specified');
		}
		else
		{
			$min = new Minify_Core($type, $source_link);
			return $min->render();
		}
	}
	
	
	public function __construct($type, $source_link)
	{
		$this->config 	= Kohana::$config->load('min');
		$lastmodified 	= '0';
		
		if( ! is_array($source_link))
		{
			$source = UTF8::trim($source_link, '/');
			
			if(file_exists($source))
			{
				$source = $source;
				$lastmodified_source[] = max($lastmodified, filemtime($source)); 
			}
			else
			{
				if($this->external($s_link))
				{
					$source = $source;
					$lastmodified_source[] = time(); 
				}
				else
				{						
					throw new HTTP_Exception_404('File ":file" not found', array(':file' => $s_link));
				}
			}
			
			$source_link = array($source);
		}
		else
		{
			$source_link_unique = array_unique($source_link);
			unset($source_link);
			
			$source = NULL;
			
			foreach($source_link_unique as $s_link)
			{			
				$s_link = UTF8::trim($s_link, '/');
				
				if(file_exists($s_link))
				{
					$source .= $s_link;
					$source_link[] = $s_link;
					$lastmodified_source[] = max($lastmodified, filemtime($s_link)); 
				}
				else
				{
					if($this->external($s_link))
					{
						$source .= $s_link;
						$source_link[] = $s_link;
						$lastmodified_source[] = time(); 
					}
					else
					{						
						throw new HTTP_Exception_404('File ":file" not found', array(':file' => $s_link));
					}
				}
			}
		}
		
		switch($type)
		{
			case 'less':
				$ext = 'css';
				break;
			case 'css':
				$ext = 'css';
				break;
			case 'js':
				$ext = 'js';
				break;
			default:
				throw new HTTP_Exception_503('Incorrect data type');
		}
			
			
		$source = md5($source);
		$this->compiled	= $this->config['output_dir'].'/'.$ext.'/'.$source.'.'.$ext;
		
		if(file_exists($this->compiled) AND 
		max($lastmodified, filemtime($this->compiled)) > max($lastmodified_source))
		{		
			$this->source_link = $this->compiled;
			return;
		}
		else
		{
			Dir::get($this->config['output_dir'].'/'.$ext);
			
			switch($type)
			{
				case 'less':
					$this->source_link = $this->less($source_link);
					break;
				case 'css':
					$this->source_link = $this->css($source_link);
					break;
				case 'js':
					$this->source_link = $this->js($source_link);
					break;
				default:
					throw new HTTP_Exception_503('Incorrect data type');
			}
		}
	}
	
	
	protected function css($source_link = NULL, $contents = NULL)
	{
		if($contents == NULL)
		{
			$contents = '';
			
			foreach ($source_link as $file) 
			{
				$contents .= file_get_contents($file);
			}
		}

		preg_match_all('/_[a-zA-Z\-]+:\s.+;|[a-zA-Z\-]+:\s_[a-zA-Z].+;/',
			$contents, $matches, PREG_PATTERN_ORDER);

		$prefixes = array("-o-", "-moz-", "-webkit-", "");
		foreach ($matches[0] as $property) 
		{
			$result = '';
			
			foreach ($prefixes as $prefix) 
			{
				$result .= str_replace("_", $prefix, $property);
			}
			
			$contents = str_replace($property, $result, $contents);
		}

		$contents = preg_replace('/(\/\*).*?(\*\/)/s', '', $contents);
		$contents = preg_replace(array('/\s+([^\w\'\"]+)\s+/', '/([^\w\'\"])\s+/'), '\\1', $contents);
		$contents = str_replace(';}', '}', $contents);
		
		if($this->config['image_to_base64'])
		{
			$contents = preg_replace('/templates\/admin\/img\/[-\w\/\.]*/ie','"data:image/".((substr("\\0",-4)==".png")?"png":"gif").";base64,".base64_encode(file_get_contents("\\0"))', $contents);
		}
		
		file_put_contents($this->compiled, $contents);
		return $this->compiled;
	}
	
	
	protected function js($source_link)
	{
		$contents = '';
		
		foreach ($source_link as $file) 
		{
			$contents .= file_get_contents($file);
		}
		
		$contents = JSMin::minify($contents);
		
		file_put_contents($this->compiled, $contents);
		return $this->compiled;
	}
	
	
	protected function less($source_link)
	{
		$contents = '';
		
		foreach ($source_link as $file) 
		{
			$contents .= file_get_contents($file);
		}
		
		if ( ! class_exists('lessc', FALSE))
		{
			require Kohana::find_file('vendor', 'lessphp/lessc.inc');
		}

		$environment = Kohana::$environment == Kohana::DEVELOPMENT;

		if ( ! $this->config['only_development'] OR $environment)
		{
			$less = new lessc;
			$contents = $less->compile($contents);
		}
		
		$this->compiled = $this->css(NULL, $contents);
		return $this->compiled;
	}
	
	
	public function render()
	{
		if(isset($this->source_link))
		{
			return Kohana::$base_url.$this->source_link;
		}
		else
		{
			return FALSE;
		}
	}
	
	
	protected function external($url)
	{
		$Headers = @get_headers($url);
		
		if(strpos($Headers[0], '200')) 
		{
			return TRUE;
		} 
		else 
		{
			return FALSE;
		}
	}
}