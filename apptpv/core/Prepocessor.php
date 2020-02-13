<?php

namespace app\core;

use MatthiasMullie\Minify;

class Prepocessor
{
    const
        BUILD = \FOLDERS\HTDOCS . 'build/',
        CACHE_FILE = \FOLDERS\CACHES  . 'cache_views.ini',
        FOLDERS_NATIVE_VIEWS = \FOLDERS\NATIVE_VIEWS,
        MAIN_PAGE = \FOLDERS\NATIVE_VIEWS . 'index.phtml';

    private
        $element,
        $cache_class_js = null,
        $cache,
        $isModified = false,
        $content,
        $queue,
        $loadeds = [],
        $components = null;

    function __construct(bool $cacheable = true)
    {
        // Se eliminan todos los archivos de la carpeta build (reinicializa)
        $this->deleteDirectory(self::BUILD);

        // Guardar en variable que componentes tenemos
        $this->search_exist_components();

        //Añade el link a bundle
        $this->queue = "<script src='./build/" . \FILE\JS . "'></script>";

        // Indicamos si cacheamos el proceso
        $this->cacheable = $cacheable;
        $this->cache = (file_exists(self::CACHE_FILE)) ? parse_ini_file(self::CACHE_FILE) : [];

        // Reseteamos el archivo de construccion build de js para agrupar todas las clases
        if (file_exists(\FILE\BUNDLE_JS)) unlink(\FILE\BUNDLE_JS);
        if (!file_exists(self::BUILD)) mkdir(self::BUILD, 0775, true);

        // Inicia compilacion de los archivos
        $this->show_files(self::FOLDERS_NATIVE_VIEWS);

        $this->cache_record($this->cache);
    }
    private function sintax_if(): void
    {
        $regex_conditional = '/@if(\s)*?\((.)*?\)(.)*?@endif/sim';
        $start_condition = '/@if(\s)*?\((.)*?\)/sim';
        $end_condition = '/@endif/i';
        $has = preg_match_all($regex_conditional, $this->content, $matches);

        if ($has) {
            foreach ($matches[0] as $key => $value) {
                // Se obtiene la condición
                if (preg_match($start_condition, $value, $matches)) {
                    $condition = preg_replace('/@if(\s)*?\(/sim', '', $matches[0]);
                    $condition = preg_replace('/\)$/', '', $condition);
                    if (empty($condition)) $condition = null;
                    $valcon = false;
                    eval('if ($condition) { $valcon = true; }');
                    if ($valcon) {
                        // Imprimimos el contenido dentro del condicional
                        $replace = preg_replace($start_condition, '', $value);
                        $replace = preg_replace($end_condition, '', $replace);
                        $this->replace($value, $replace);
                    } else {
                        // Eliminamos todo el condicional 
                        $this->replace($value, '');
                    }
                }
            }
        }
    }

    private function isComponent(): bool
    {
        return ($this->path == \APP\VIEWS\COMPONENTS || $this->path == \APP\VIEWS\MYCOMPONENTS);
    }
    // Funcion que aplica una sintaxis propia  a las vistas
    // Todos los comandos de las vista deben enpezar por --
    private function sintax()
    {
        $this->autoId();
        $this->includes();
        $this->search_components($this->content);
        $this->sintax_if();
        $this->sintax_for();
        $this->sintax_vars();
        // Encapsulación de los estilos
        foreach ($this->tags('style') as $tag) {
            $this->add_style_scope($tag);

            if ($tag->get('lang') == 'less') {
                $this->less($tag->content());
            }
            // eliminamos el argumento scoped
            $tag->del('scoped');
            $tag->del('lang');
        }
        // Encapsulación de los scripts
        foreach ($this->tags('script') as $tag) {
            $this->add_script_scope($tag);
            $tag->del('scoped');
        }
    }
    private function add_script_scope(Tag $tag) : self
    {
        if ($tag->get('scoped')) {
            $lastContent = $tag->content();
            $tag->content(
               "(function(){
                   $lastContent
               })()"
            );
            $this->replace($lastContent, $tag->content());        
        }
        return $this;
    }
    private function add_style_scope(Tag $tag): self
    {
        if ($tag->get('scoped')) {
            $lastContent = $tag->content();
            $nameTag = $this->search_first_tag();
            $first = $this->tags($nameTag)[0];

            // Quitamos las reglas principales
            $content = $tag->content();
            $content = preg_replace('/@import.*?;/', '', $content);
            $content = preg_replace('/@charser.*?;/', '', $content);

            // Se coloca el id a los estilos 
            $tag->content("#{$first->id()}{{$content}}");

            $this->replace($lastContent, $tag->content());
        };
        return $this;
    }
    /**
     * Funcion auxiliar para reemplazar el contenido de la pagina
     */
    private function replace($arg, $val = null)
    {
        switch (gettype($arg)) {
            case 'string':
                $this->content = str_replace($arg, $val, $this->content);
                break;
            case 'array':
                foreach ($arg as $key => $val) {
                    $this->content = str_replace($key, $val, $this->content);
                }
                break;
        }
    }
    private function sintax_for()
    {
        if (
            $len = preg_match_all('/@for\s*\((.*?)\)(.*?)@endfor/sim', $this->content, $matches)
        ) {
            for ($i = 0; $i < $len; $i++) {
                $res = '';
                $cond = $matches[1][$i];
                $body = $matches[2][$i];
                $struct = $matches[0][$i];

                if (is_string($cond)) $arr = json_decode($cond);

                foreach ($arr as $key => $value) {
                    $str = str_replace('$$value', $value, $body);
                    $res .= str_replace('$$key', $key, $str);
                }
                $this->replace($struct, $res);
            }
        }
    }
    private function compress_code($code)
    {
        $search = array(
            '/\>[^\S ]+/s',  // remove whitespaces after tags
            '/[^\S ]+\</s',  // remove whitespaces before tags
            '/(\s)+/s'       // remove multiple whitespace sequences
        );

        $replace = array('>', '<', '\\1');
        $code = preg_replace($search, $replace, $code);
        return $code;
    }
    /**
     * Extrae el tag del html (Solo el primero)
     * @param tag html
     * @return array[contenido , atributos[]]
     */
    private function extract($tag): array
    {
        $attr = [];
        $regex = "#<$tag\s*([^>]*)>(.*?)<\/\\1>#s";
        if (
            preg_match($regex, $this->content, $matches)
        ) {
            $args = explode(" ", $matches[1]);

            foreach ($args as $match) {
                if ($match) {
                    $arr = explode('=', $match);
                    if (isset($arr[1])) {
                        $a = trim($arr[1], '"');
                        $attr[$arr[0]] = trim($a, "'");
                    } else {
                        $attr[$arr[0]] = true;
                    }
                }
            }
        }

        return [
            'content' => $matches[2] ?? null,
            'attr'    => $attr ?? null
        ];
    }
    private function get_content(String $file): String
    {
        $this->content = file_get_contents($file);
        $this->element = new Tag($this->content); 
        return $this->content;
    }
    // Elimina los comentarios html
    function remove_comments()
    {
        $this->content = preg_replace('/<!--(.|\s)*?-->/', '', $this->content);
        $this->content = preg_replace('/[^\:]\/\/(.*)/', '', $this->content);
    }
    private function less(String $content)
    {
        //COMPILAMOS LESS
        $less = new \lessc;

        $content_less = $less->compile($content);

        // MINIMIFICAMOS
        $minifier = new Minify\CSS;
        $minifier->add($content_less);
        $content_min = $minifier->minify();

        $this->replace($content, $content_min);
    }
    // Generador de ids únicos
    private function uniqid()
    {
        // Se le añade unprefijo para que siempre empieze por una letra
        return uniqid('id');
    }
    private function search_first_tag()
    {
        $regex = "/\<(\w*?) ([^>]*?)>(.*?)<\/\\1>/si";
        preg_match($regex, $this->content, $matches);
        return $matches[1] ?? false;
    }
    /**
     *   Devuelve todos los argumentos de un tag
     *  @return array de la clase Tag
     */
    private function tags(string $tag): array
    {
        $regex = "/\<($tag) ([^>]*?)>(.*?)<\/\\1>/si";
        /**
         * 0 -> Todo
         * 1 -> tag
         * 2 -> argimentos
         * 3 -> contenido
         */
        if (
            $len = preg_match_all($regex, $this->content, $matches)
        ) {
            for ($i = 0; $i < $len; $i++) {
                $a[$i] = new Tag($matches[0][$i]);
            }
        }
        return $a ?? [];
    }
    private function includes()
    {
        $has = preg_match_all('/\s\@include\s*\((.*?)\)\s/', $this->content, $matches);
        if ($has) {
            $len = count($matches[0]);
            for ($i = 0; $i < $len; $i++) {
                $str = "<?php include({$matches[1][$i]})?>";
                $this->content = str_replace($matches[0][$i], $str, $this->content);
            }
        }
        return $has;
    }
    // Busca sibolo $ para y lo reemplaza por variables php
    private function sintax_vars()
    {
        if (
            preg_match_all('#\$\$(\w+\-?\w*)#is', $this->content, $matches)
        ) {

            for ($i = 0; $i < count($matches[0]); $i++) {
                $str = '<?=$' . trim($matches[1][$i] ?? null, '\$') . '?>';
                $this->content = str_replace($matches[0][$i], $str, $this->content);
            }
        }
    }
    // Comando --id -> Genera un id único para todo el documento.
    private function autoId(): string
    {
        $id = $this->uniqid();
        $this->content = str_ireplace('--id', $id, $this->content);
        return $this->content;
    }
    // Funcion preprocesadora de los archivos
    // Lee archivos de directorios y los directorios anidados
    private function show_files(String $path)
    {
        $dir = opendir($path);

        while ($current = readdir($dir)) {
            if ($current != "." && $current != "..") {
                $build_path = str_replace(self::FOLDERS_NATIVE_VIEWS, '', $path . $current);
                $file = $path . $current;
                $this->file = $file;
                $file_build =  self::BUILD . $build_path;
                if (is_dir($file)) {
                    
                    // DIRECTORIOS 
                    if (!file_exists($file_build)) mkdir($file_build, 0775, true);
                    $this->show_files($file . '/');

                } else {

                    // ARCHIVOS
                    // Obtenemos el ontenido del archivo (Se crea $this->content)
                    $this->get_content($file);

                    // Quitamos los comentarios 
                    $this->remove_comments();

                    // No se la aplicamos a los componentes para que mantengan la encapsulación
                    $this->path = $path;

                    if (!$this->isComponent()) $this->sintax();
                    
                    AKII :: 
                    $this->build_js();

                    if ($file == self::MAIN_PAGE) $this->queue();

                    // Compresión salida html
                    if (!ENV) $this->content  = $this->compress_code($this->content);

                    file_put_contents($file_build, $this->content, LOCK_EX);
                
                }
            }
        }
    }
    // Carga de los componentes creados en la carpeta
    private function search_exist_components()
    {
        $this->components = [];
        $folder = \APP\VIEWS\MYCOMPONENTS;
        if (!file_exists($folder)) mkdir($folder, 0777, true);
        $gestor = opendir($folder);
        // Busca los componentes en la carpeta de vistas componentes
        while (($file = readdir($gestor)) !== false) {
            if ($file != "." && $file != "..") {
                $arr = explode('.', $file);
                $this->components[] = $arr[0];
            }
        }
    }
    // Buscar componentes existentes en el contenido 
    // el parametro ha de ser enviado por referencia
    private function search_components(&$content): void
    {
        foreach ($this->components as $component) {

            $regex = "#<($component)(\s[^>\/]*)?>(.*?)<\/\g{1}>#s";
            // Primero buscamos los que contienen tag de cierre ya que pueden contener otros elementos anidados
            if (
                preg_match_all(
                    $regex,
                    $content,
                    $matches
                )
            ) {
                $this->process_components($matches, $content);
            }
            // Después buscamos los que no tienen tag de cierre
            if (
                preg_match_all(
                    "/<\s*($component)(\s.*?|\s*?)\/>/s",
                    $this->content,
                    $matches
                )
            ) {
                $this->process_components($matches, $content);
            }
        }
    }
    /**
     * Procesa los componentes personalizados de las plantillas 
     */
    private function process_components($matches, &$content)
    {
        // Transforma en una clase componente
        $len = count($matches[0]);
        for ($i = 0; $i < $len; $i++) {
            // Convertimos la cadena en arreglos para pasar los datos al componente
            $argData = $this->args_to_array($matches[2][$i]);
            // Creamos la la instancia de clase 
            $typeComponent = $matches[1][$i];

            // Comprobamos si el componente alberga contenido

            // Si existe lo preprocesamos
            if (isset($matches[3])) {
                // Si encuentra contenido en el componente comprueba que si tiene componentes anidados
                $str = str_replace('"', "'", $matches[3][$i]);
                $component_content = ', ' .  isset($matches[3]) ? '"' . $str . '"' : '';
            } else {
                $component_content = 'false';
            }
            // Instanciamos la clase de componentes
            $replace = "<?php new \app\core\Components('$typeComponent',$argData, $component_content);?>";
            $content = str_replace($matches[0][$i], $replace, $content);

            ob_start(); # apertura de bufer
            file_put_contents(\VIEWS\MYCOMPONENTS . "content.tmp.phtml", $content);
            include(\VIEWS\MYCOMPONENTS . "content.tmp.phtml");

            $this->content = ob_get_contents();
            ob_end_clean(); # cierre de bufer
            $this->search_components($this->content);
        }
        return true;
    }
    private function args_to_array($content)
    {
        $str_data = '';
        $regex = '#(.+?)\s*=\s*(["\'])(.+?)\g{2}#s';
        if (
            preg_match_all($regex, $content, $matches_component)
        ) {
            $len_c = count($matches_component[0]);
            // Cambio de comillas para que se adecue a la sintaxis JSON
            for ($j = 0; $j < $len_c; $j++) {
                $str_key = trim($matches_component[1][$j]);
                $str_value = trim($matches_component[3][$j]);

                $value = str_replace("'", '"', $str_value);
                $key = trim(str_replace("'", '"', $str_key));
                $str_data .=  "'$key'=>'$value',";
            }
        }

        $str_data = trim($str_data, ',');
        return " Array($str_data)";
    }
    private function queue()
    {
        $content = $this->content;
        $new_content = str_replace('</head>', $this->queue . '</head>', $content);
        $this->content = $new_content;
    }

    /**
     * Extraemos las clases de los componentes 
     * y las cargamos en un ambito global
     */
    private function build_js(){
        prs($this->file);
        $regex = '/class [\w+?](.*?)/si';
        
        foreach( $this->tags('script') as $tag ){
            pr($tag->content());
            if (preg_match_all($regex, $tag->content(), $matches)) {
            prs('AKIIIIIIIIIIIIIIIIIIII', $matches);
            }
        }
    }
    private function build_js1($class_js)
    {
        $strFile = file_exists(\FILE\BUNDLE_JS)
            ? \file_get_contents(\FILE\BUNDLE_JS)
            : '';

        // Buscamos clases js en el archivo
        $regex = '/class [A-Za-z0-9]{1,150}/';
        if (preg_match($regex, $class_js, $r)) {
            // Buscamos clases padre y las agregamos a la carga principal de la aplicación 
            $regex_extends = '/extends [A-Za-z0-9]*/';
            if (preg_match($regex_extends, $class_js, $match)) {

                $main_class = explode(' ', $match[0])[1];
                $src = "<script src='./js/$main_class.min.js'></script>";
                if (!strpos($this->queue, $src)) $this->queue = $src . $this->queue;
            }

            // Creamos el archivo de la clase
            $ac = explode(' ', $r[0]);
            $nameClass = $ac[1];

            // MINIMIFICAMOS JS
            $minifier = new Minify\JS;
            $minifier->add($class_js);
            $strFile .= $minifier->minify();
            file_put_contents(\FILE\BUNDLE_JS, $strFile);

            // Eliminamos el tag script del documento
            $this->content = str_replace($class_js, '', $this->content);

            // Registramos la clase como cargada 
            $this->loadeds[] = $nameClass;

            // Añadimos una cola para agregar los enlaces en el index o puerta principal 
            //$this->queue .= "<script src='./build/js/$nameClass.js'></script>";

            // Buscamos la clase en la sesión
            if (!empty($this->cache_class_js) && in_array($ac[1], $this->cache_class_js)) {
                // si ya esta cargada
                $this->content = str_replace($class_js, '', $this->content);
            } else {
                // Cargamos en la cache de la sesión
                $this->cache_class_js[] = $ac[1];
            }
        }
        // MINIMIFICAMOS JS
        $minifier = new Minify\JS;
        $minifier->add($class_js);
        $replace = $minifier->minify();
        return  str_replace($class_js, $replace, $this->content);
    }
    private function cache_record(array $cache)
    {
        if ($this->isModified) {
            $out = '';
            foreach ($cache as $k => $v) {
                $out .= $k . ' = "' . $v . '"' . "\n";
            }
            file_put_contents(self::CACHE_FILE, $out, LOCK_EX);
            return true;
        } else return false;
    }
    private function deleteDirectory($dir)
    {
        if (!$dh = @opendir($dir)) return;
        while (false !== ($current = readdir($dh))) {
            if ($current != '.' && $current != '..') {
                if (!@unlink($dir . '/' . $current))
                    $this->deleteDirectory($dir . '/' . $current);
            }
        }
        closedir($dh);
        rmdir($dir);
    }
}
