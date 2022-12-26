<?php

namespace Webrium;

use Webrium\Directory;
use Webrium\File;

class View
{

    private static $cacheMode = 0;



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
     *   (pages that are loaded as components in the main page)
     *   are created in one page, which makes the include operation
     *   less, resulting in less overhead and faster speed.
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
        ob_start();
        include $file_name;
        return ob_get_clean();
    }

    public static function _setParams($name, $array = [])
    {
        foreach ($array as $key => $value) {
            $GLOBALS[$key] = $value;
        }
    }


    /**
     * It produces intermediate codes or actually the same main php codes
     */
    private static function intermediateCodeGeneration($html)
    {

        $html = '<?php foreach ($GLOBALS as $key => $value) {${$key}=$value;}; $_all = $GLOBALS; ?>' . $html;

        self::syntaxAnalyser('@foreach', $html, '<?php foreach', ': ?>');
        self::syntaxAnalyser('@for', $html, '<?php for', ': ?>');
        self::syntaxAnalyser('@if', $html, '<?php if', ': ?>');
        self::syntaxAnalyser('@elseif', $html, '<?php elseif', ': ?>');
        self::syntaxAnalyser('@while', $html, '<?php while', ': ?>');
        self::syntaxAnalyser('@lang', $html, '<?php echo lang', '; ?>');
        self::syntaxAnalyser('@load', $html, '<?php echo load', '; ?>');
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

        self::replaceSpecialSymbol('{{', '}}', $html, '<?php echo htmlspecialchars(', '); ?>');
        self::replaceSpecialSymbol('{!!', '!!}', $html, '<?php echo ', '; ?>');

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

        $arr = \substr($html, \strpos($html, $find));
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
                "Syntax error in '$error' in " . end(self::$views)
            );
        }
    }

    private static function staticCacheGenerator($main_name, $hash_name)
    {

        $html = file_get_contents(Directory::path('render_views') . '/' . $hash_name);
        // die("name:$html");
        self::$list_json = null;
        $view_json = self::getListJson();
        $findblock_start = '<!-- auto include -->';
        $findblock_end = '<!-- end include -->';

        $static_list = [];
        do {
            $start_pos = strpos($html, $findblock_start);

            if ($start_pos !== false) {
                $main_block = substr($html, $start_pos);
                $end_pos = strpos($main_block, $findblock_end);
                $main_block = substr($main_block, 0, $end_pos + strlen(($findblock_end)));

                $find_view_start = '<?php echo view';
                $view_name_pos = strpos($main_block, $find_view_start);
                $view_block = substr($main_block, $view_name_pos + strlen($find_view_start));
                $params_block = substr($view_block, 0, strpos($view_block, '; ?>'));
                $view_block = substr($view_block, 1);
                $eee2 = strpos($view_block, ',');
                if ($eee2 !== false) {
                    $view_block = substr($view_block, 0, $eee2);
                }

                $eee2 = strpos($view_block, ')');
                if ($eee2 !== false) {
                    $view_block = substr($view_block, 0, $eee2);
                }

                $view_block = str_replace("'", '', $view_block);
                $view_name = str_replace('"', '', $view_block) . ".php";

                if (strpos($view_name, '$') !== false) {
                    $html = str_replace($findblock_start, '', $html);
                    $html = str_replace($findblock_end, '', $html);

                    continue;
                }
                // echo 'vn:'.json_encode($view_name )."<br>";
                // die(json_encode($view_json['list']));

                $get = file_get_contents(Directory::path('render_views') . '/' . $view_json['list'][$view_name]);
                // die('ok:'.$view_name.'  res=>'.htmlspecialchars($get));
                $html = str_replace($main_block, $get, $html);

                $find_script_text = '<?php foreach ($GLOBALS as $key => $value) {${$key}=$value;}; $_all = $GLOBALS; ?>';
                $find_script_pos = strpos($html, $find_script_text) + strlen($find_script_text);
                $temp = substr($html, $find_script_pos);
                $temp = str_replace($find_script_text, '', $temp);
                $temp = substr($html, 0, $find_script_pos) . $temp;
                $html = $temp;
                $html = "<?php Webrium\View::_setParams$params_block;?>" . $html;

                // die(htmlspecialchars($html));
            }
        } while ($start_pos !== false);

        file_put_contents(Directory::path('render_views') . "/static_" . $hash_name, $html);
        $view_json['static_list'][$main_name] = "static_" . $hash_name;
        file_put_contents(Directory::path('render_views') . '/list.json', json_encode($view_json));
    }



    public static function replaceSpecialSymbol($start, $end, &$html, $prefix, $suffix)
    {
        $html = \str_replace($start, $prefix, $html);
        $html = \str_replace($end, $suffix, $html);
    }


    /**
     * Deletes all cache files
     */
    private static function clearCaches()
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
        $time_end = microtime(true);


        /*
        | Create and save file list information
        */
        $list_file_content = ['created_at' => date('Y-m-d H:i:s'), 'time' => time(), 'exec_time' => ($time_end - $time_start), 'list' => $list];
        file_put_contents(Directory::path('render_views') . '/list.json', json_encode($list_file_content));


        /*
        | Static cache files are generated with a loop over all created files
        */
        foreach ($list_file_content['list'] as $original_name => $hash_name) {
            self::staticCacheGenerator($original_name, $hash_name);
        }
    }


    /**
     * Deleting old cache files and rebuilding files and caches
     */
    private static function reCompile()
    {
        self::clearCaches();
        self::compile();
    }






    private static $list_json;
    private static function getListJson()
    {

        if (file_exists(Directory::path('render_views') . '/list.json') == false) {
            return false;
        }

        $string = file_get_contents(Directory::path('render_views') . '/list.json');

        if (self::$list_json == null) {
            self::$list_json = json_decode($string, true);
        }
        return self::$list_json;
    }

    public static function render($view_name, $params = [])
    {
        $file_name = $view_name . '.php';


        self::_setParams('', $params);

        if (self::$cacheMode >= 1) {

            $json = self::getListJson();


            if ($json === false) {
                View::clearCaches();
                View::compile();
                $json = self::getListJson();
            }


            if (self::$cacheMode == 2) {
                $hash_file = $json['static_list'][$file_name] ?? false;
            } else if (self::$cacheMode == 1) {
                $hash_file = $json['list'][$file_name] ?? false;
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


        return $html;
    }
}
