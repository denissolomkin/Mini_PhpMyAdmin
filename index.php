<?php session_start();

class CONTROLLER
{
    private $model;
    private $view;

    function __construct()
    {
        $this->view = new VIEW;
        $this->model = new MODEL;
    }

    public function start()
    {
        //////////////////////
        //  Configuration   //
        //////////////////////

        include_once('config.php');
        if($_GET['database'])   $db=$_GET['database']; elseif ($_SESSION['connect']['database']) $db=$_SESSION['connect']['database'];
        if($_GET['connect'])   $connect=$_GET['connect'];

        //////////////////////
        //   Common Data    //
        //////////////////////

        $this->model->connectDB($connect); //connect to DB
        $this->model->setDB($db); //select DB
        $this->model->openDBL(); //SQLite for queries
        $left=$this->model->getMenu(); // get left menu

        //////////////////////
        // Basic Controller //
        //////////////////////

        if($_GET['query'])                    $tables=array('table'=>$this->model->setQuery(str_replace('"',"'",$_GET['query'])));   /* query by href */
        elseif($_POST['queryDO']=='save' OR ($_POST['query'] && !$_POST['queryDO'])){       /* query by form */
                if($_POST['queryDO']=='save') $this->model->saveDBL();                      /* query by form with save */
                $tables=array('table'=>$this->model->setQuery(str_replace('"',"'",$_POST['query'])));}
        elseif($_POST['queryDO']=='delete')  {$this->model->deleteDBL();$tables=$this->model->getTables();}   /* query by form with action delete*/
        elseif($_GET['table'])                $table=$this->model->getTable($_GET['table']);    /* set concrete table */
        else                                  $tables=$this->model->getTables();                /* output tables */
        if(($_GET['location'] && !$_GET['database']) || !$db) $left=$tables=$this->model->getDBs();   /* reset databases */
        $left['query']=$this->model->getDBL(); /* storage of queries */

        //////////////////////
        //     Templates    //
        //////////////////////

        $this->view->loadTpl('top');
        $this->view->loadTpl('left',$left);
        $this->view->loadTpl('middle');
        $this->view->loadTpl('breadcrumbs',$this->model->getBreadcrumbs());
        $this->view->loadTpl('query', $this->model->getQuery());
        $this->view->loadTpl('tables',$tables);
        $this->view->loadTpl('table', $table);
        $this->view->loadTpl('bottom');
    }

}

class MODEL
{
    private $connect;
    private $query;
    private $link;
    private $dbl;

    ////////////////////
    //   Connecting   //
    ////////////////////

    /* connect to database */
    public function connectDB($connect)
    {
        $this->connect= $_SESSION['connect']=$connect;
        if(!$this->connect['location'])  $this->connect['error']='Please, set location';
        elseif(!$this->connect['login'])     $this->connect['error']='Please, set login';
        elseif(!$this->connect['password'])  $this->connect['error']='Please, set password';
        elseif (!@$this->link = mysql_connect($this->connect['location'], $this->connect['login'], $this->connect['password']))
            $this->connect['error']='Check location, login and password';
        else
            $this->connect['success']='Connection is success';
        return $this->connect;
    }

    /* set database */
    public function setDB($db)
    {
        mysql_select_db($this->connect['database']=$_SESSION['connect']['database']=$db,$this->link);
    }

    ////////////////////
    //  SQLite Block  //
    ////////////////////

    public function openDBL($file='dbl_queries.db')
    {
        $this->dbl = new SQLite3($file);
        if (!$this->dbl) exit("Error with open DBL");
        $this->dbl->exec('CREATE TABLE IF NOT EXISTS queries (id INTEGER PRIMARY KEY AUTOINCREMENT, query)');
    }

    public function getDBL()
    {
        $results = $this->dbl->query('SELECT * FROM queries');
        while ($row = $results->fetchArray(SQLITE3_ASSOC))
            $array[]=array('id'=>$row['id'],'query'=>$this->debugQuery($row['query']));
        return $array;
    }

    public function deleteDBL()
    {
        $this->dbl->exec('DELETE FROM queries WHERE id = '.$_POST['queryID']);
        unset($_POST);
    }

    public function saveDBL()
    {
        $_POST['query']=str_replace('"',"'",$_POST['query']);
        if($_POST['queryID'])
            $this->dbl->exec('UPDATE queries SET query="'.$_POST['query'].'" WHERE id = '.$_POST['queryID']);
        else
        {
            $this->dbl->exec('INSERT INTO queries (query) VALUES ("'.$_POST['query'].'")');
            $_POST['queryID']=$this->dbl->lastInsertRowid();
        }
    }


    ////////////////////
    //  Format Output //
    ////////////////////

    /* get Query */
    public function getQuery()
    {
        return $this->query;
    }

    /* get Breadcrumbs */
    public function getBreadcrumbs()
    {
        return array(
            'location'=>$this->connect['location'],
            'database'=>$this->connect['database'],
            'table'=>$this->connect['table']);
    }

    /* debug Query */
    public function debugQuery($sql)
    {
        return str_ireplace(
            array('*','SHOW ','SELECT ','UPDATE ','DELETE ','INSERT ','INTO','VALUES','FROM','LEFT','JOIN','WHERE','LIMIT','ORDER BY','GROUP BY','AND','OR ','DESC','ASC','ON '),
            array("<FONT COLOR='#FF6600'><B>*</B></FONT>","<FONT COLOR='#00AA00'><B>SHOW</B> </FONT>","<FONT COLOR='#00AA00'><B>SELECT</B> </FONT>","<FONT COLOR='#00AA00'><B>UPDATE</B> </FONT>","<FONT COLOR='#00AA00'><B>DELETE</B> </FONT>","<FONT COLOR='#00AA00'><B>INSERT</B> </FONT>","<FONT COLOR='#00AA00'><B>INTO</B></FONT>","<FONT COLOR='#00AA00'><B>VALUES</B></FONT>","<FONT COLOR='#00AA00'><B>FROM</B></FONT>","<FONT COLOR='#00CC00'><B>LEFT</B></FONT>","<FONT COLOR='#00CC00'><B>JOIN</B></FONT>","<FONT COLOR='#00AA00'><B>WHERE</B></FONT>","<FONT COLOR='#AA0000'><B>LIMIT</B></FONT>","<FONT COLOR='#00AA00'><B>ORDER BY</B></FONT>","<FONT COLOR='#00AA00'><B>GROUP BY</B></FONT>","<FONT COLOR='#0000AA'><B>AND</B></FONT>","<FONT COLOR='#0000AA'><B>OR</B> </FONT>","<FONT COLOR='#0000AA'><B>DESC</B></FONT>","<FONT COLOR='#0000AA'><B>ASC</B></FONT>","<FONT COLOR='#00DD00'><B>ON</B> </FONT>"),
            preg_replace("/[`'\"][a-zA-Z0-9_.]+[`'\"]/i", "<FONT COLOR='#FF6600'>$0</FONT>", $sql, -1)
        );
    }

    ////////////////////
    //    SQL Logic   //
    ////////////////////

    /* get Order By for tables */
    public function getOrder()
    {
        if ($_GET['order'])
            $this->query['last']=preg_replace("!( |)order by ([a-zA-Z0-9, a-zA-Z0-9\d\D]+)!si",null,$this->query['last']).' ORDER BY `' . str_replace(' ', '` ', $_GET['order']);
    }

    /* set LIMIT for tables */
    public function setLimit()
    {
        if ($this->query['count']>30)
            $this->query['last']=preg_replace("!( |)LIMIT ([a-zA-Z, \d\D]+)!si",null,$this->query['last']).' LIMIT '.($_GET['from']?$_GET['from']:0).',30';
    }

    /* get Query */
    public function setQuery($sql,$options=array('limit'=>true,'order'=>true))
    {
        $this->query['last']=$sql;
        if($options[order]) $this->getOrder();
        $this->query['timer'] = microtime(true);
        $this->query['count']= @mysql_result(mysql_query('SELECT COUNT(*) FROM ('.$sql.') t'),0);
        //$this->query['count']=@mysql_result(mysql_query(preg_replace(array("!select(.*?)from!si","!group by([a-zA-Z\d\D]+)!si"),array('SELECT COUNT(*) FROM',null),$sql)),0);
        if($options[limit]) $this->setLimit();
        $res=mysql_query($this->query['last']);
        $this->query['timer'] = '(Query took'.sprintf(' %.4f sec)', microtime(true)-$this->query['timer']);
        $this->query['error'] = mysql_error();

        if ($res)
            while ($row = mysql_fetch_assoc($res))
                $array[]=$row;

        $this->query['debug'] = $this->debugQuery($this->query['last']);

        return $array;
    }

    ////////////////////
    //  Queries Sets  //
    ////////////////////

    /* select tables from database */
    public function getDBs()
    {
        unset($this->connect['database']);
        $sql = 'SELECT DISTINCT(`table_schema`)  FROM information_schema.tables';
        return array('object'=>'database','table'=>$this->setQuery($sql,false));
    }

    /* select tables for left menu */
    public function getMenu()
    {
        $sql= 'SELECT `TABLE_NAME` FROM information_schema.tables WHERE `table_schema` = "'.$this->connect['database'].'" GROUP BY `TABLE_NAME`';
        return array('table'=>$this->setQuery($sql,array('limit'=>false,'order'=>false)));
    }

    /* select tables from database */
    public function getTables()
    {
        $sql='select `TABLE_NAME`, `ENGINE`, `TABLE_ROWS` from information_schema.tables where `table_schema` = "'.$this->connect['database'].'" group by `TABLE_NAME`';
        return array('table'=>$this->setQuery($sql));
    }

    /* select data from table */
    public function getTable($table)
    {
        $this->connect['table']=$table;
        if($_GET['structure'])
            $sql= 'select `COLUMN_NAME`, `COLUMN_DEFAULT`,`IS_NULLABLE`,`COLUMN_TYPE`, `CHARACTER_MAXIMUM_LENGTH`, `COLUMN_KEY`, `EXTRA` from information_schema.columns where `table_schema` = "'.$this->connect['database'].'" and `table_name`="'.$this->connect['table'].'"';
        else
            $sql= 'SELECT * FROM `'.$table.'`';

        return array('table'=>$this->setQuery($sql));
    }


    //////////////////////
    // Generate Feature //
    //////////////////////

    /* set generate data to table */
    public function genTable($table)
    {
        $sql= 'select * from information_schema.columns where TABLE_NAME="'.$table['table'].'" AND table_schema = "'.$this->db['database'].'"order by table_name,ordinal_position';
        if ($res=mysql_query($sql)) {
            /* select columns from table */
            while ($row = mysql_fetch_assoc($res)) {
                if($row['EXTRA']=='auto_increment' || $row['COLUMN_DEFAULT']) continue;
                $columns[$row['COLUMN_NAME']]=$row;
            }
            /* generate random data for columns*/
            do{
                $i++;
                foreach($columns as $column)
                    $generate[$i][$column['COLUMN_NAME']]=$this->setColumn($column);
                $query[]='('.implode(',',$generate[$i]).')';
            } while ($i<$table['rows']);
        }

        $sql= ("INSERT INTO `".$table['table']."` (`".(implode("`,`",array_keys($generate[1])))."`) VALUES".(implode(",",$query)));
        mysql_query($sql);
        return $generate;
    }


    /* set generate data for column */
    private function setColumn($col=array())
    {

        switch ($col['DATA_TYPE']) {
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
            case 'int':
                return $this->genInt($col['COLUMN_TYPE']);
                break;
            case 'float':
            case 'double':
            case 'real':
                return $this->genFloat($col['COLUMN_TYPE']);
                break;
            case 'numeric':
            case 'decimal':
                return $this->genDec($col['COLUMN_TYPE']);
                break;
            case 'enum':
            case 'set':
                return $this->genSet($col['COLUMN_TYPE']);
                break;
            case 'char':
            case 'varchar':
            case 'tinytext':
            case 'text':
            case 'mediumtext':
            case 'longtext':
            case 'tinyblob':
            case 'blob':
            case 'mediumblob':
            case 'longblob':
                return $this->genChar($col['CHARACTER_MAXIMUM_LENGTH']);
                break;
            case 'timestamp':
            case 'datetime':
                return $this->genDate('Y-m-d h:i:s');
                break;
            case 'time':
                return $this->genDate('h:i:s');
                break;
            case 'date':
                return $this->genDate('Y-m-d');
                break;
            case 'year':
                return ($col['COLUMN_TYPE']=='year(4)' ? $this->genDate('Y') : $this->genDate('y'));
                break;
            default:
                return '0';
        }
    }

    /* CHAR type of column */
    private function genChar($len=1)
    {
        if($len>256) $len=rand(32,($len>1024?512:$len));
        $values="0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ     ";
        for($i=0;$i<$len;$i++)
            $ret.=$values[rand(0,66)];
        return '\''.$ret.'\'';
    }

    /* INT type of column */
    private function genInt($type)
    {
        $int=array('tinyint'=>1,'smallint'=>2,'mediumint'=>3,'int'=>4,'bigint'=>8);
        $max=(int)pow(256,$int[substr($type,0,(strpos($type,'(')))]);
        if($max==0) $max=PHP_INT_MAX;
        if(strstr($type,'unsigned'))
        {
            $min=0; $max=-1;
        }
        else
        {
            $min=$max/-2;$max=$max/2-1;
        }
        return mt_rand($min,$max);
    }

    /* SET type of column */
    private function genSet($type)
    {
        $set=explode(',',substr($type,strpos($type,'(')+1,(strpos($type,')')-1-strpos($type,'('))));
        return $set[rand(0,count($set)-1)];
    }
    /* DEC type of column */
    private function genDec($type)
    {
        $minus=array(1,-1);
        $len=explode(',',substr($type,strpos($type,'(')+1,(strpos($type,')')-1-strpos($type,'('))));
        /* if has scale, when -1 of precision */
        if($len[1]) $len[0]--;
        /* if has unsigned, when +1 of precision, else shuffle $minus*/
        strstr($type,'unsigned') ? $len[0]++ : shuffle ($minus);
        $max=pow(10,$len[0])-1;
        return $minus[0]*rand(0,$max)/(pow(10,$len[1]));
    }

    /* FLOAT type of column */
    private function genFloat($type)
    {
        $minus=array(1,-1);
        strstr($type,'unsigned') ? : shuffle ($minus);
        return $minus[0]*rand()/100;
    }


    /* DATE type of column */
    private function genDate($format)
    {   $start = mktime(0,0,0,1970,1,1);
        $end  = time();
        return '\''.date($format,rand($start,$end)).'\'';
    }
}


class VIEW
{
    public function loadTpl($tpl,$data=true)
    {
        if($data==false) return;
        include('tpl/'.$tpl.".html");
    }
}

$control=new CONTROLLER;
$control->start();
?>