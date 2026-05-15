<?php
require_once __DIR__ . "/vendor/autoload.php";
use Tracy\Debugger;
// Modo desarrollo
Debugger::enable(Debugger::Development);
//Debugger::$dumpTheme = 'dark';
Debugger::$maxLength = 2048;
Debugger::$showLocation = true;

Debugger::getBar()->addPanel(
    new class implements \Tracy\IBarPanel {
        public function getTab(){
            return '<span title="Información del Sistema">ℹ️</span>';
        }
        public function getPanel(){
            $load = function_exists("sys_getloadavg") ? sys_getloadavg() : [0, 0, 0];
            return '
                <h1>Información del Servidor</h1>
                <div class="tracy-inner"><table><tr><th>PHP</th><td>' .
                PHP_VERSION .
                "</td></tr><tr><th>Memoria</th><td>" .
                round(memory_get_peak_usage() / 1024 / 1024, 2) .
                " MB</td></tr><tr><th>Carga CPU</th><td>" .
                implode(" | ", $load) .
                "</td></tr><tr><th>Server</th><td>" .
                $_SERVER["SERVER_SOFTWARE"] .
                '</td></tr>
                </table></div><br><h1>Uso</h1><div class="tracy-inner"><table><tr><th>Timer</th><td>' .
                'Tracy\Debugger::timer();<br>Tracy\Debugger::timer(\'nombreTimer\');' .
                "</td></tr><tr><th>Mostrar</th><td>" .
                'bdump($var, "Descripcion");' .
                '</td></tr><tr><th>Hacer trac a una variable</th><td>$trac_var[\'linea 5\'] = $var;<br>$trac_var[\'linea 15\'] = $var;<br>//Mostrar el trac de valores<br>bdump($trac_var, "\$sql");</td></tr>
                <tr><th>Depurador SQL, onScriptInit</th><td>$this->Db = new DebugPDOProxy($this->Db);</td></tr>
                </table>
                </div>';
        }
    },
);

Debugger::getBar()->addPanel(
    new class implements \Tracy\IBarPanel {
        public function getTab()
        {
            return '<span title="Request Dumps">🌐</span>';
        }

        public function getPanel()
        {
            return '
                <h1>Request Data</h1>
                <h2>REQUEST</h2>' .
                $_SERVER["REQUEST_METHOD"] .
                " " .
                $_SERVER["REQUEST_URI"] .
                "<br>" .
                $_SERVER["SCRIPT_NAME"] .
                "<br>" .
                $_SERVER["HTTP_REFERER"] .
                "<br>" .
                "Code : " .
                http_response_code() .
                '<h2>$_GET</h2>' .
                \Tracy\Dumper::toHtml($_GET) .
                '<h2>$_POST</h2>' .
                \Tracy\Dumper::toHtml($_POST) .
                '<h2>$_COOKIE</h2>' .
                \Tracy\Dumper::toHtml($_COOKIE) .
                '<h2>$_SERVER</h2>' .
                \Tracy\Dumper::toHtml($_SERVER) .
                "";
        }
    },
);

error_reporting(E_ALL);
ini_set("display_errors", 1);

class DebugPDOProxy
{
    private $db;
    private $donde;
    public function __construct($db, $donde = 0) // 1 en la barra, 0 en pantalla
    {
        $this->db = $db;
        $this->donde = $donde;
    }
    public function Execute($sql)
    {
        return $this->run("EXECUTE", $sql, fn() => $this->db->Execute($sql));
    }
    public function GetAll($sql)
    {
        return $this->run("GETALL", $sql, fn() => $this->db->GetAll($sql));
    }

    public function SelectLimit($sql, $limit, $offset = 0)
    {
        return $this->run("SELECT LIMIT", $sql, fn() => $this->db->SelectLimit($sql, $limit, $offset), [
            "limit" => $limit,
            "offset" => $offset,
        ]);
    }

    public function UpdateBlob($table, $column, $var, $where, $blobtype = "BLOB")
    {
        return $this->run("UPDATE_BLOB", "TABLE: $table | COLUMN: $column", fn() => $this->db->UpdateBlob($table, $column, $var, $where, $blobtype), [
            "where" => $where,
            "blob_type" => $blobtype,
            "size" => round(strlen((string) $var) / 1024, 2) . " kb",
        ]);
    }

    public function BeginTrans()
    {
        return $this->run("TRANS_BEGIN", "START TRANSACTION", fn() => $this->db->BeginTrans());
    }

    public function CommitTrans($ok = true)
    {
        return $this->run("TRANS_COMMIT", "COMMIT", fn() => $this->db->CommitTrans($ok), [
            "status" => $ok ? "Success" : "Failed/Rollback forced",
        ]);
    }

    public function RollbackTrans()
    {
        return $this->run("TRANS_ROLLBACK", "ROLLBACK", fn() => $this->db->RollbackTrans());
    }

    private function run($type, $sql, $callback, $extra = [])
    {
        $start = microtime(true);
        $result = $callback();
        $time = (microtime(true) - $start) * 1000;
        
        $rows = 0;
        $size = 0;

        // 1. DETERMINAR FILAS (ROWS)
        if ($type === "EXECUTE") {
            // Para INSERT, UPDATE, DELETE usamos Affected_Rows de la conexión
            $rows = $this->db?->Affected_Rows() ?? 0;
        } elseif (is_array($result)) {
            // Para GetAll() que devuelve arrays
            $rows = count($result);
        } elseif (is_object($result) && method_exists($result, "RecordCount")) {
            // Para Execute() de SELECT que devuelve un RecordSet
            $rows = $result->RecordCount();
        }

        // 2. DETERMINAR TAMAÑO (ESTIMACIÓN SIN INTERFERENCIA)
        if (is_array($result) && $rows > 0) {
            $size = round((strlen(serialize(reset($result))) * $rows) / 1024, 2) . ' kb';
        }elseif (is_object($result) && $rows > 0) {
            if ($rows > 0 && isset($result->fields) && (is_array($result->fields) || is_object($result->fields))) {
                $fila = (array)$result->fields;
                $totalRowWeight = strlen(serialize($fila));
                $keysWeight = strlen(serialize(array_keys($fila)));
                $netRowWeight = max(0, $totalRowWeight - ($keysWeight / 2)); 
                $size = round(($netRowWeight * $rows) / 1024,2). ' kb aprox';
                //$rowWeight = strlen(serialize((array)$result->fields));
                //$size = round(($rowWeight * $rows) / 1024,2). ' kb aprox';
            } else {
                $size = '0 kb';
            }
            //$columnCount = method_exists($result, "FieldCount") ? $result->FieldCount() : 0;
            //$size = ($columnCount * $rows * 10) / 1024;
            //$extra['estimated'] = true; 
        }

        // 3. CAPTURAR METADATOS ADICIONALES
        $lastId = null;
        // Solo intentar capturar si es un EXECUTE Y la consulta contiene la palabra INSERT
        if ($type === "EXECUTE" && stripos($sql, 'INSERT') !== false) {
            $lastId = $this->db?->Insert_ID();
            // Si devuelve 0, lo ponemos como null para no ensuciar el log
            if ($lastId == 0) $lastId = null;
        }

        $data = array_merge(
            [
                "sql"     => $type . ' | ' . $sql,
                "stats"   => round($time, 3) . " ms | " . $rows . " rows | " . $size
            ],
            $extra
        );
        if($lastId != null){
            $data['lastId'] = $lastId;
        }
        if($this->db->ErrorMsg() != null){
            $data['error'] = $this->db->ErrorMsg();
        }        

        if ($this->donde) {
            \Tracy\Debugger::barDump($data, "SQL" . ($type ? " | $type" : ""));
        } else {
            dump($data);
        }

        return $result;
    }    

    public function __call($name, $args)
    {
        return $this->db->$name(...$args);
    }
}

// en onScriptInit
//$this->Db = new DebugPDOProxy($this->Db);
//$this->Db = new DebugPDOProxy($this->Db, 1);
?>
