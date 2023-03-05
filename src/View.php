<?php

namespace Webrium;

use Webrium\Directory;
use Webrium\File;

class View
{

    private static $cacheMode = 0;

    private static $scriptInjectingVariables = '<?php $_all = \Webrium\View::getParams(); foreach ($_all as $key => $value) {${$key}=$value;}; ?>';

    private static $list_json;



    /**
     * Mode and how to do cache
     * The default is 0
     * 
     * Mode 0 is suitable for development time, and 
     *  with any change in the main template, the executable 
     *  file is automatically generated when the page is called.
     *
     * In mode 1, executable files are routed with higher speed
     *  and less overhead than mode 0. Changes to the template file
     *  are not detected automatically and it is necessary to execute
     *  the `recompile` or `clearCaches` function once.

     * In mode 2, a static cache is created. All nested pages 
     *  (pages that are loaded as components in the main page)
     *  are created in one page, which makes the include operation
     *  less, resulting in less overhead and faster speed.
     *  Changes to the template file are not detected automatically
     *  and it is necessary to execute the `recompile` or `clearCaches` function once.
     * 
     * @param $mode int
     */
    public static function setCacheMode(int $mode = 0)
    {
        self::$cacheMode = $mode;
    }


    private static function includeHTML($file_name)
    {
        

        Debug::$ErrorView=true;

        ob_start();
        include $file_name;
        $view = ob_get_clean();
    
        if (Debug::status()==false) {
          return $view;
        }
        else {
          Event::emit('error_view',['message'=>Debug::getErrorStr(),'line'=>Debug::getErrorLine(),'file'=>Debug::getErrorFile()]);
    
          if (Debug::getShowErrorsStatus()) {
            return Debug::getHTML();
          }
    
        }
    }

    public static function loadview($view)
    {
      return self::includeHTML(Directory::path('views')."/$view.php");
    }

    public static function setParams($array = [])
    {
        foreach ($array as $key => $value) {
            $GLOBALS['framework'][$key] = $value;
        }
    }

    public static function getParams(){
        return $GLOBALS["framework"]??[];
    }



    private static function _getNameOfViewArgs($name,$a=[]){
        return $name;
    }


    /**
     * It produces intermediate codes or actually the same main php codes
     */
    private static function intermediateCodeGeneration($html)
    {
        $html = self::$scriptInjectingVariables . $html;
        
        self::syntaxAnalyser('@foreach', $html, '<?php foreach', ': ?>');
        self::syntaxAnalyser('@for', $html, '<?php for', ': ?>');
        self::syntaxAnalyser('@if', $html, '<?php if', ': ?>');
        self::syntaxAnalyser('@elseif', $html, '<?php elseif', ': ?>');
        self::syntaxAnalyser('@while', $html, '<?php while', ': ?>');
        self::syntaxAnalyser('@lang', $html, '<?php echo lang', '; ?>');
        self::syntaxAnalyser('@loadview', $html, '<?php echo loadview', '; ?>');
        self::syntaxAnalyser('@url', $html, '<?php echo url', '; ?>');
        self::syntaxAnalyser('@old', $html, '<?php echo old', ';?>');
        self::syntaxAnalyser('@message', $html, '<?php echo message', '; ?>');

        if (self::$cacheMode == 2) {
            self::syntaxAnalyser('@view', $html, "<!-- auto include -->" . '<?php echo view', '; ?>' . '<!-- end include -->');
        } else {
            self::syntaxAnalyser('@view', $html, '<?php echo view', '; ?>');
        }


        $html = str_replace('@endforeach', '<?php endforeach; ?>', $html);
        $html = str_replace('@endfor', '<?php endfor; ?>', $html);
        $html = str_replace('@else', '<?php else: ?>', $html);
        $html = str_replace('@endif', '<?php endif; ?>', $html);
        $html = str_replace('@endwhile', '<?php endwhile; ?>', $html);

        $html = str_replace('@end', '?>', $html);
        $html = str_replace('@php', '<?php', $html);

        $html = preg_replace('/@(\{{2})((?:[^}{]+|(?R))*+)(\}{2})/', '@_DONOTCHANGSTART$2@_DONOTCHANGEEND',$html);

        self::replaceSpecialSymbol('{{', '}}', $html, '<?php echo htmlspecialchars(', '); ?>');
        self::replaceSpecialSymbol('{!!', '!!}', $html, '<?php echo ', '; ?>');
        self::ReplaceSpecialSymbol('@_DONOTCHANGSTART','@_DONOTCHANGEEND',$html,'{{','}}');

        return $html;
    }


    /**
     * Creates an executable file from a template
     */
    private static function codeGenerator($view_name)
    {

        $dir_path = Directory::path('render_views');

        // Create a file hash and generate a new file name
        $hash_name = md5_file($view_name) . '.php';

        // Add path to save the file
        $hash_file_path = "$dir_path/$hash_name";

        // Get the contents of the template file
        $html = file_get_contents($view_name);


        if (file_exists($hash_file_path) == false) {

            // Executable code is created
            $html = self::intermediateCodeGeneration($html);

            // Save the file
            file_put_contents($hash_file_path, $html);
        }


        return ['hash_name' => $hash_name, 'view_name' => $view_name, 'html' => $html];
    }

    private static function syntaxAnalyser($find, &$html, $prefix, $suffix)
    {

        $error = false;
        $find_first = strpos($html, $find);

        if($find_first ==false){
            return;
        }

        $arr = \substr($html, $find_first );
        $explode = \explode($find, $arr);

        foreach ($explode as $line) {
            if ($line) {

                $s = 0;
                $e = 0;

                $finish = false;

                foreach (str_split($line) as $key => $str) {
                    if ($str == '(') {
                        $s++;
                    } elseif ($str == ')') {
                        $e++;
                    }

                    if ($s > 0 && $e == $s) {
                        $finish = $key;
                        break;
                    }
                }

                if ($finish) {
                    $block = \substr($line, 0, $finish + 1);
                    $html = \str_replace("$find$block", "$prefix$block$suffix", $html);
                } else {
                    $error = $find;
                }
            }
        }

        if ($error) {
            throw new \Exception(
                "Syntax error in '$error' in " . ''
            );
        }
    }

    private static function staticCacheGenerator($main_name, $hash_name)
    {

        $html = file_get_contents(Directory::path('render_views') . '/' . $hash_name);

        $view_json = self::currentJsonFile(true);

        $sign_block_start = '<!-- auto include -->';
        $sign_block_end = '<!-- end include -->';

        $setParamsList = [];

        do {

            $start_block_pos = strpos($html, $sign_block_start);
            $end_block_pos = strpos($html, $sign_block_end);

            if ($start_block_pos == false && $end_block_pos == false) {
                break;
            } else {

                $main_start_pos = ($start_block_pos+ strlen($sign_block_start));
                $main_block = substr($html,$main_start_pos , $end_block_pos-$main_start_pos);

                $find_view_start = '<?php echo view';
                $find_view_end = '; ?>';
                $args_block = str_replace("$find_view_start(", '', $main_block);
                $args_block = str_replace(")$find_view_end", '', $args_block);

                $args =  explode(',',$args_block, 2);

                
                $block_view_name = str_replace("'", '', $args[0]);
                $block_view_name = str_replace('"', '', $block_view_name) . ".php";

                
                if (strpos($block_view_name, '$') !== false) {
                    $html = str_replace($main_block, '', $html);
                    $html = str_replace($main_block, '', $html);

                    continue;
                }

                
                $get = file_get_contents(Directory::path('render_views') . '/' . self::findHashFile($block_view_name, 'list'));
                $html = str_replace("$sign_block_start$main_block$sign_block_end", $get, $html);
                
                if(count($args)==2){
                    $setParamsList[] =  '<?php Webrium\View::setParams('.$args[1].');?>';
                }

            }
        } while ($start_block_pos != false && $end_block_pos != false);
                

        $html = str_replace(self::$scriptInjectingVariables, '', $html);
        $html = self::$scriptInjectingVariables.$html;

        foreach($setParamsList as $set){
            $html= $set.$html;
        }
        
        file_put_contents(Directory::path('render_views') . "/static_" . $hash_name, $html);
        $view_json['static_list'][$main_name] = "static_" . $hash_name;
        file_put_contents(Directory::path('render_views') . '/list.json', json_encode($view_json));
        self::currentJsonFile(true);
    }



    private static function replaceSpecialSymbol($start, $end, &$html, $prefix, $suffix)
    {
        $html = \str_replace($start, $prefix, $html);
        $html = \str_replace($end, $suffix, $html);
    }


    /**
     * Deletes all cache files
     */
    public static function clearCaches()
    {
        $files = glob(Directory::path('render_views') . '/*');
        foreach ($files as $file) { // iterate files
            if (is_file($file) && strpos($file, '.php') > 0) {
                unlink($file); // delete file
            }
        }
    }


    /**
     * Returns a list of all files created in the views directory
     * 
     * @param $dir [false|string]
     * @return Array
     */
    private static function getAll($dir = false)
    {
        $list = [];

        if (!$dir) {
            $dir = Directory::path('views');
        }

        $files = scandir($dir);

        unset($files[array_search('.', $files, true)]);
        unset($files[array_search('..', $files, true)]);

        // prevent empty ordered elements
        if (count($files) < 1) {
            return [];
        }

        foreach ($files as $file) {
            if (is_dir($dir . '/' . $file)) {
                $dir_list = self::getAll($dir . '/' . $file);
                $list = array_merge($list, $dir_list);
            } else {
                $list[] = $dir . '/' . $file;
            }
        }

        return $list;
    }





    /**
     * Creates original and executable codes from templates
     */
    private static function compile()
    {

        // To calculate the duration of generating files
        $time_start = microtime(true);

        $files = self::getAll();

        $views_path = Directory::path('views') . '/';


        /*
        | Creating a list of hashed file names
        */
        $list = [];
        foreach ($files as $file) {
            $result = self::codeGenerator($file);
            $result['view_name'] = str_replace($views_path, '', $result['view_name']);
            $list[$result['view_name']] = $result['hash_name'];
        }

        // To calculate the duration of generating files


        /*
        | Create and save file list information
        */
        $list_file_content = ['created_at' => date('Y-m-d H:i:s'), 'time' => time(), 'list' => $list];
        file_put_contents(Directory::path('render_views') . '/list.json', json_encode($list_file_content));


        /*
        | Static cache files are generated with a loop over all created files
        */
        foreach ($list_file_content['list'] as $original_name => $hash_name) {
            self::staticCacheGenerator($original_name, $hash_name);
        }

        $time_end = microtime(true);
        $file = self::currentJsonFile(true);
        $file['exec_time']=($time_end - $time_start);
        file_put_contents(Directory::path('render_views') . '/list.json', json_encode($file));
    }


    /**
     * Deleting old cache files and rebuilding files and caches
     */
    public static function reCompile()
    {
        self::clearCaches();
        self::compile();
    }


    private static function currentJsonFile($forcePull = false)
    {
        if (self::$list_json == null || $forcePull == true) {

            if (file_exists(Directory::path('render_views') . '/list.json') == false) {
                return false;
            }

            $string = file_get_contents(Directory::path('render_views') . '/list.json');
            self::$list_json = json_decode($string, true);
        }


        return self::$list_json;
    }


    private static function findHashFile($name, $type = 'list')
    {
        return self::currentJsonFile()[$type][$name] ?? false;
    }

    private static function findFileNameWithHash($hash_name, $type = 'list')
    {
        foreach(self::currentJsonFile()[$type] as $name=>$hash){
            if($hash == $hash_name){
                return $name;
            }
        }

        return $hash_name;
    }



    public static function render($view_name, $params = []){
        $file_name = $view_name . '.php';

        self::setParams($params);


        if (self::$cacheMode >= 1) {

            $json = self::currentJsonFile();

            if ($json === false) {
                View::clearCaches();
                View::compile();
                $json = self::currentJsonFile();
            }




            if (self::$cacheMode == 2) {
                $hash_file = self::findHashFile($file_name, 'static_list');
            } else if (self::$cacheMode == 1) {
                $hash_file = self::findHashFile($file_name, 'list');
            }


            $hash_file_path = Directory::path('render_views') . '/' . $hash_file;

            if ($hash_file && file_exists($hash_file_path)) {
                $html = self::includeHTML($hash_file_path);

            } else {
                View::clearCaches();
                View::compile();
                $html = self::render($view_name, $params);
            }
        }

        else{
            $view = Directory::path('views');
            $render_path = Directory::path('render_views');
            $render = self::codeGenerator("$view/$file_name");
            $html = self::includeHTML("$render_path/".$render['hash_name']);
        }


        return $html;
    }




    public static function getOrginalNameByHash($hashName)
    {
        $file_name = basename($hashName);
        self::reCompile();
        self::currentJsonFile(true);
        return self::findFileNameWithHash($file_name);
    }
}
