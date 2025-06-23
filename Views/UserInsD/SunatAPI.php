<?php
/**
 * Clase SunatAPI - Manejo de servicios de SUNAT
 * Especializada en validación de RUC y consultas de contribuyentes
 */

require_once __DIR__ . '/config_sunat.php';

class SunatAPI {
    
    private $api_config;
    private $current_api;
    
    public function __construct($api_name = null) {
        $this->current_api = $api_name ?? SUNAT_API_DEFAULT;
        $this->api_config = SUNAT_APIs[$this->current_api];
    } 
    /**
     * Valida RUC con fallback a APIs alternativas
     * @param string $ruc
     * @return array
     */
    public function validarRUCConFallback($ruc) {
        $apis = array_keys(SUNAT_APIs);
        $ultimoError = '';
        
        foreach ($apis as $apiName) {
            try {
                $this->current_api = $apiName;
                $this->api_config = SUNAT_APIs[$apiName];
                
                $resultado = $this->validarRUC($ruc);
                
                if ($resultado['success']) {
                    return $resultado;
                }
                
                $ultimoError = $resultado['message'];
                
            } catch (Exception $e) {
                $ultimoError = $e->getMessage();
                continue;
            }
        }
        
        return [
            'success' => false,
            'message' => 'Error en todas las APIs disponibles. Último error: ' . $ultimoError,
            'data' => null
        ];
    }
    
    /**
     * Obtiene estadísticas de uso de las APIs
     * @return array
     */
    public function obtenerEstadisticasAPIs() {
        try {
            $pdo = getDBConnection();
            
            $sql = "SELECT 
                        tipo_consulta as api,
                        COUNT(*) as total_consultas,
                        SUM(respuesta_exitosa) as consultas_exitosas,
                        ROUND((SUM(respuesta_exitosa) / COUNT(*)) * 100, 2) as porcentaje_exito,
                        MAX(fecha_consulta) as ultima_consulta
                    FROM sunat_validaciones_log 
                    WHERE fecha_consulta >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY tipo_consulta
                    ORDER BY total_consultas DESC";
            
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            logError("Error al obtener estadísticas APIs: " . $e->getMessage());
            return [];
        }
    }

    
    /**
     * Valida un RUC consultando la API de SUNAT
     * @param string $ruc - Número de RUC a validar
     * @return array - Datos del contribuyente o error
     */
    public function validarRUC($ruc) {
        // Validación básica del formato RUC
        if (!$this->validarFormatoRUC($ruc)) {
            logError("RUC con formato inválido: {$ruc}");
            return [
                'success' => false,
                'message' => 'Formato de RUC inválido',
                'data' => null
            ];
        }
        
        try {
            // Log de intento de validación
            logError("Iniciando validación de RUC: {$ruc}", ['api' => $this->current_api]);
            
            $response = $this->realizarConsulta($ruc);
            
            if ($response === false) {
                throw new Exception('Error al conectar con el servicio de SUNAT');
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Error al procesar respuesta de SUNAT: ' . json_last_error_msg());
            }
            
            $resultado = $this->procesarRespuestaSUNAT($data, $ruc);
            
            // Log del resultado
            logError("Validación completada para RUC: {$ruc}", [
                'success' => $resultado['success'],
                'api' => $this->current_api
            ]);
            
            return $resultado;
            
        } catch (Exception $e) {
            logError("Error en validación RUC {$ruc}: " . $e->getMessage(), ['api' => $this->current_api]);
            return [
                'success' => false,
                'message' => 'Error en consulta SUNAT: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Valida el formato básico del RUC
     * @param string $ruc
     * @return bool
     */
    private function validarFormatoRUC($ruc) {
        // Solo valida que sean 11 dígitos numéricos
        return preg_match('/^\d{11}$/', $ruc);
        // RUC debe tener 11 dígitos
        // if (strlen($ruc) !== 11 || !ctype_digit($ruc)) {
        //     return false;
        // }
        
        // // Validación del dígito verificador
        // $factor = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        // $suma = 0;
        
        // for ($i = 0; $i < 10; $i++) {
        //     $suma += $ruc[$i] * $factor[$i];
        // }
        
        // $resto = $suma % 11;
        // $digitoVerificador = $resto < 2 ? $resto : 11 - $resto;
        
        // return $digitoVerificador == $ruc[10];
    }
    
    /**
     * Procesa la respuesta de la API de SUNAT
     * @param array $data
     * @param string $ruc
     * @return array
     */

    private function procesarRespuestaSUNAT($data, $ruc) {
        // Si la API actual es factiliza, ajusta el mapeo de campos
        if ($this->current_api === 'factiliza') {
            if (!isset($data['success']) || !$data['success'] || !isset($data['data'])) {
                return [
                    'success' => false,
                    'message' => 'RUC no encontrado o inactivo en Factiliza',
                    'data' => null
                ];
            }
            $d = $data['data'];
            return [
                'success' => true,
                'message' => 'RUC válido y activo',
                'data' => [
                    'ruc' => $d['numero'] ?? $ruc,
                    'razon_social' => $d['nombre_o_razon_social'] ?? '',
                    'nombre_comercial' => $d['nombre_comercial'] ?? '',
                    'direccion' => $d['direccion'] ?? '',
                    'ubigeo' => $d['ubigeo_sunat'] ?? '',
                    'distrito' => $d['distrito'] ?? '',
                    'provincia' => $d['provincia'] ?? '',
                    'departamento' => $d['departamento'] ?? '',
                    'estado' => $d['estado'] ?? '',
                    'condicion' => $d['condicion'] ?? '',
                    'tipo_contribuyente' => $d['tipo_contribuyente'] ?? $d['tipoContribuyente'] ?? ''
                ]
            ];
        }

        // Para otras APIs, usa el mapeo anterior
        if (!isset($data['success']) || !$data['success']) {
            return [
                'success' => false,
                'message' => 'RUC no encontrado o inactivo en SUNAT',
                'data' => null
            ];
        }
        return [
            'success' => true,
            'message' => 'RUC válido y activo',
            'data' => [
                'ruc' => $ruc,
                'razon_social' => $data['razonSocial'] ?? '',
                'nombre_comercial' => $data['nombreComercial'] ?? '',
                'direccion' => $data['direccion'] ?? '',
                'ubigeo' => $data['ubigeo'] ?? '',
                'distrito' => $data['distrito'] ?? '',
                'provincia' => $data['provincia'] ?? '',
                'departamento' => $data['departamento'] ?? '',
                'estado' => $data['estado'] ?? '',
                'condicion' => $data['condicion'] ?? '',
                'tipo_contribuyente' => $data['tipoContribuyente'] ?? ''
            ]
        ];
    }
    
    /**
     * Realiza la consulta HTTP a la API configurada
     * @param string $ruc
     * @return string|false
     */
    private function realizarConsulta($ruc) {
        $url = str_replace(['{ruc}', '{token}'], [$ruc, $this->api_config['token']], $this->api_config['url']);
        
        $curl = curl_init();
        
        $headers = $this->api_config['headers'];
        if (isset($this->api_config['token']) && strpos(implode('', $headers), '{token}') !== false) {
            $headers = array_map(function($header) {
                return str_replace('{token}', $this->api_config['token'], $header);
            }, $headers);
        }
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => SUNAT_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => SUNAT_CONNECT_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $this->api_config['method'],
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        if ($error) {
            throw new Exception('Error cURL: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('Error HTTP: ' . $httpCode);
        }
        
        return $response;
    }
}