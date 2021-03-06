<?php 

function generate_menu($slug,$parent_id=0) {
    $menus = DB::table('cms_menus')
    ->join('cms_menus_groups','cms_menus_groups.id','=','id_cms_menus_groups')
    ->where('cms_menus_groups.slug',$slug)
    ->where('cms_menus.parent_id_cms_menus',$slug)
    ->select('cms_menus.*')
    ->get();

    $class = ($parent_id==0)?'menu_crudbooster_'.$slug:'submenu_crudbooster_'.$slug;
    echo "<ul class='$class'>";
    foreach($menus as $menu) {
        $label = $menu->name;
        switch($menu->menu_type) {
            case 'Custom Link':
                $link = str_replace('[domain]',url('/'),$menu->menu_link);
            break;
            case 'Posts':
                $posts = DB::table('cms_posts')->where('id',$menu->id_cms_posts)->first();
                $link = url('view/'.$posts->slug);
            break;
            case 'Pages':
                $pages = DB::table('cms_pages')->where('id',$menu->id_cms_pages)->first();
                $link = url('page/'.$pages->slug);
            break;
            case 'Categories':
                $categories = DB::table('cms_posts_categories')->where('id',$menu->id_cms_posts_categories)->first();
                $link = url('category/'.$categories->slug);
            break;
        }

        $sub_count = DB::table('cms_menus')->where('parent_id_cms_menus',$menu->id)->count();
        if($sub_count>0) {
            echo "<li><a href='$link'>$label</a>";
            generate_menu($slug,$menu->id);
            echo "</li>";
        }else{
            echo "<li><a href='$link'>$label</a></li>";
        }        
    }

    echo "</ul>";
}

function get_where_value($name) {
    $input = array("label"=>"cms menus groups","name"=>"id_cms_menus_groups","type"=>"hidden","value"=>\Request::get('where')[$name]);
    return $input;
}
function get_columns_table($table) {
    $cols = DB::getSchemaBuilder()->getColumnListing($table);
    $result = array();
    $result = $cols;

    $new_result = array(); 
    foreach($result as $ro) {
        if($ro=='created_at' || $ro=='updated_at' || $ro=='id') continue;
        $new_result[] = $ro;
    }
    return $new_result;
}
function get_namefield_table($coloms) {
    $name_col_candidate = array("name","nama","title","judul","content");   
    $name_col = '';
    foreach($coloms as $c) {
        foreach($name_col_candidate as $cc) {
            if( strpos($c,$cc) !==FALSE ) {
                $name_col = $c;
                break;
            }
        }
        if($name_col) break;
    }
    if($name_col == '') $name_col = 'id';
    return $name_col;
}
function is_exists_controller($table) {
    $controllername = ucwords(str_replace('_',' ',$table));
    $controllername = str_replace(' ','',$controllername).'Controller';
    $path = "app/Http/Controllers/";
    $path2 = "app/Http/Controllers/ControllerMaster/";
    if(file_exists($path.'Admin'.$controllername.'.php') || file_exists($path2.'Admin'.$controllername.'.php') || file_exists($path2.$controllername.'.php')) {
        return true;
    }else{
        return false;
    }
}

function generate_controller($table,$name='') {
        $exception = ['slug'];

        $controllername = ucwords(str_replace('_',' ',$table));        
        $controllername = str_replace(' ','',$controllername).'Controller';

        if($name) {
            $controllername = ucwords(str_replace(array('_','-'),' ',$name));            
            $controllername = str_replace(' ','',$controllername).'Controller';
        }

        $path = "app/Http/Controllers/";
        $image_candidate = array("image","picture","file","foto","gambar","photo","thumb","thumbnail");

        if(file_exists($path.'Admin'.$controllername.'.php') || file_exists($path.'ControllerMaster/Admin'.$controllername.'.php')) {
            return 'Admin'.$controllername;
            exit;
        }
        $coloms   = get_columns_table($table);
        $name_col = get_namefield_table($coloms);
                
$php = '
<?php 
namespace App\Http\Controllers;

use Session;
use Request;
use DB;
use App;
use Route;
use Validator;

class Admin'.$controllername.' extends Controller {

    public function __construct() {
        $this->table         = "'.$table.'";
        $this->primkey       = "id";
        $this->titlefield    = "'.$name_col.'";
        $this->theme         = "admin.default"; 
        $this->prefixroute   = "admin/";
        $this->index_orderby = ["id"=>"desc"];

        $this->col = array();
';

        foreach($coloms as $c) {
            $label = str_replace("id_","",$c);
            $label = ucwords(str_replace("_"," ",$label));
            $field = $c;

            if(in_array($field, $exception)) continue;

            if(substr($field,0,3)=='id_') {
                $jointable = str_replace('id_','',$field);
                $joincols = get_columns_table($jointable);
                $joinname = get_namefield_table($joincols);
                $php .= "\t\t".'$this->col[] = array("label"=>"'.$label.'","field"=>"'.$field.'","join"=>"'.$jointable.','.$joinname.'");'."\n";
            }else{
                $image = '';
                if(in_array($field, $image_candidate)) $image = ',"image"=>true';
                $php .= "\t\t".'$this->col[] = array("label"=>"'.$label.'","field"=>"'.$field.'" '.$image.');'."\n";    
            }
        }

        $php .= "\n\t\t".'$this->form = array();'."\n";

        foreach($coloms as $c) {
        $add_attr = '';
        $label = str_replace("id_","",$c);
        $label = ucwords(str_replace("_"," ",$label));      
        $field = $c;

        if(in_array($field, $exception)) {
            $php .= "\t\t".'$this->form[] = array("name"=>"'.$field.'","type"=>"hidden");'."\n";
            continue;
        }

            $typedata = DB::connection()->getDoctrineColumn($table, $field)->getType()->getName();
            $typedata = strtolower($typedata);
            switch($typedata) {
                default:
                case 'varchar':
                case 'char':
                $type = "text";
                break;
                case 'text':
                case 'longtext':
                $type = 'textarea';
                break;
                case 'date':
                $type = 'date';
                break;
                case 'datetime':
                case 'timestamp':
                $type = 'datetime';
                break;
            }

        $datatable = '';
        if(substr($field,0,3)=='id_') {
            $jointable = str_replace('id_','',$field);
            $joincols = get_columns_table($jointable);
            $joinname = get_namefield_table($joincols);
            $datatable = ',"datatable"=>"'.$jointable.','.$joinname.'"';
            $type = 'select';
        }

        
        if(in_array($field, $image_candidate)) {
            $type = 'upload';
        }

        if($field == 'latitude' || $field == 'longitude') {
            $type = 'hidden';            
        }

        if($field == 'latitude') {
            $add_attr .= ',"googlemaps"=>true';
        }

        $php .= "\t\t".'$this->form[] = array("label"=>"'.$label.'","name"=>"'.$field.'","type"=>"'.$type.'" '.$datatable.' '.$add_attr.' );'."\n";   
        }

$php .= '                 
        
        //You may use this bellow array to add relational data to next tab 
        $this->form_tab = array();

        //You may use this bellow array to add relational data to next area or element, i mean under the existing form 
        $this->form_sub = array();

        //You may use this bellow array to add some or more html that you want under the existing form 
        $this->form_add = array();                                                                                      
        


        //No need chanage this constructor
        $this->constructor();
    }


    public function hook_before_index(&$result) {
        //Use this hook for manipulate query of index result 
        
    }
    public function hook_html_index(&$html_contents) {
        //Use this hook for manipulate result of html in index 

    }
    public function hook_before_add(&$arr) {
        //Use this hook for manipulate data input before add data is execute 

    }
    public function hook_after_add($id) {
        //Use this hook if you want execute other command after add function called 

    }
    public function hook_before_edit(&$arr,$id) {
        //Use this hook for manipulate data input before update data is execute 

    }
    public function hook_after_edit($id) {
        //Use this hook if you want execute other command after update data called 

    }
    public function hook_before_delete($id) {
        //Use this hook if you want execute other command before delete command called 

    }
    public function hook_after_delete($id) {
        //Use this hook if you want execute other command after delete command called 

    }
    
}
        ';

        $php = trim($php);

        //create file controller
        file_put_contents($path.'Admin'.$controllername.'.php', $php);
        return 'Admin'.$controllername;
    }

function get_string_between($string, $start, $end){
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}

function SplitSQL($file, $delimiter = ';')
{
    set_time_limit(0);

    $result = array();

    $response = array();

    $file = fopen($file, 'r');

    $query = array();

    while (feof($file) === false)
    {
        $query[] = fgets($file);

        if (preg_match('~' . preg_quote($delimiter, '~') . '\s*$~iS', end($query)) === 1)
        {
            $query = trim(implode('', $query));

            if(substr($query, 0, 2)!="/*") {

                $data = array(); 

                if(substr($query, 0,12)=='CREATE TABLE') {
                    $data['type'] = 'CREATE_TABLE';             
                    $data['table'] = $table = get_string_between($query,"CREATE TABLE `","` (");
                    $response['tables'][] = $data['table'];
                    $response['tables_create'][$table] = $query;
                }elseif (substr($query, 0,11)=='INSERT INTO') {
                    $data['type'] = 'INSERT_INTO';
                    $data['table'] = $table = get_string_between($query,"INSERT INTO `","` (");
                    $response['tables_insert'][$table][] = $query;
                }elseif (substr($query, 0, 11)=='ALTER TABLE') {
                    $data['type'] = 'ALTER_TABLE';
                    $data['table'] = $table = get_string_between($query,"ALTER TABLE `","`");
                    $response['tables_alter'][$table][] = $query;
                }

                $data['query'] = $query;

                $result[] = $data;
            }

        }

        if (is_string($query) === true)
        {
            $query = array();
        }
    }
    fclose($file);

    $response['result'] = $result;

    return $response;
}

function super_unique($array,$key)
{
   $temp_array = array();
   foreach ($array as &$v) {
       if (!isset($temp_array[$v[$key]]))
       $temp_array[$v[$key]] =& $v;
   }
   $array = array_values($temp_array);
   return $array;
}
function time_elapsed_string($datetime='',$datetimeto, $full = false) {
    $now = new DateTime;
    if($datetime!='') {
        $now = new DateTime($datetime);
    }
    $ago = new DateTime($datetimeto);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ' : 'just now';
}
function human_filesize($bytes, $decimals = 2) {
  $sz = 'BKMGTP';
  $factor = floor((strlen($bytes) - 1) / 3);
  return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
}

function get_size($url) {
    $head = array_change_key_case(get_headers($url, TRUE));
    return $head['content-length'];
}

function send_email($to,$subject,$data,$from='',$template='') {
     $setting = DB::table('cms_settings')->where('name','like','smtp%')->get();
     $set = array();
     foreach($setting as $s) {
        $set[$s->name] = $s->content;
     }     

    \Config::set('mail.driver',$set['smtp_driver']);
    \Config::set('mail.host',$set['smtp_host']);
    \Config::set('mail.port',$set['smtp_port']);
    \Config::set('mail.username',$set['smtp_username']);
    \Config::set('mail.password',$set['smtp_password']);

    $template = ($template)?:"emails.blank";
    $from = ($from)?:$set['smtp_username'];
    \Mail::send($template,$data,function($message) use ($to,$subject,$from) {
        $message->to($to);
        $message->from($from);
        $message->subject($subject);
    });
}

function unparse_url($parsed_url) { 
  $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : ''; 
  $host     = isset($parsed_url['host']) ? $parsed_url['host'] : ''; 
  $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : ''; 
  $user     = isset($parsed_url['user']) ? $parsed_url['user'] : ''; 
  $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : ''; 
  $pass     = ($user || $pass) ? "$pass@" : ''; 
  $path     = isset($parsed_url['path']) ? $parsed_url['path'] : ''; 
  $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : ''; 
  $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : ''; 
  return urldecode("$scheme$user$pass$host$port$path$query$fragment"); 
}

function show_value($id,$tabel,$show='value',$empty=''){

    $queries = DB::table($tabel)
        ->where('id','=',$id)
        ->orderBy('id','DESC')
        ->first();

    if(empty($queries))
    {
        $the_value =  $empty;
    } else {
        $the_value =  $queries->$show;
    }

    return $the_value;          
}

function showValue_byField($string,$id,$table,$show='value',$empty=''){

    $queries = DB::table($table)
        ->where($string,'=',$id)
        ->orderBy('id','DESC')
        ->first();

    if(empty($queries))
    {
        $the_value =  $empty;
    } else {
        $the_value =  $queries->$show;
    }

    return $the_value;          
}

function get_setting($name){
    $query = DB::table('cms_settings')->where('name',$name)->first();
    return $query->content;
}

function array_table($table,$orderby='id',$empty=''){
    $query = DB::table($table)
        ->orderby($orderby,'ASC')
        ->get();

    if (empty($query)) {
        $result = $empty;
    }else{
        $result = $query;
    }

    return $result;
}

function slug($title,$table,$where="title",$id=NULL){
    $slug_title = str_slug($title, "-");

    $queries = DB::table($table)
        ->where($where,'=',$title)  
        ->where('slug',$slug_title)      
        ->orderBy('id','DESC');
    if($id) {
        $queries->where('id','!=',$id);
    }
    $queries = $queries->first();


    if(is_null($queries)){
        $slug      = trim(preg_replace('/[^a-z0-9]+/i', '-', $slug_title), '-');
        $the_value =  strtolower($slug);
    }else{  
        $string    = $queries->$where;
        $slug      = trim(preg_replace('/[^a-z0-9]+/i', '-', $string), '-');    
        $lastplust = substr(strrchr($slug, '-'), 1)+1;
        $the_value = strtolower($slug_title."-".$lastplust);

    }
    return $the_value;

}
