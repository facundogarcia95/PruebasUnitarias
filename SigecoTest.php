<?php

use App\Helper\Conexiones;
use \App\Modelo as Modelo;
use FMT\Helper\Arr;


class SigecoTest extends \PHPUnit\Framework\TestCase
{
		/* 
				SITUADOS EN PROYECTO SIGECO: Ejecutamos el siguiente comando por consola. Nota: En el comando se define el interprete a utilizar.

				/usr/bin/php74 ./vendor/bin/phpunit pruebas/SigecoTest 
		
		*/

	public static $id_lote;
	public static $id_lote_cuit;

	/**
	 * Test de ALTA DE LOTE
	 *
	 * @return Conexiones
	 */
	public function testAltaLote(){
		$cnx	= new Conexiones();
		$cnx->beginTransaction();
		try {

			//TRAE LOS CAMPOS Y LOS DATOS CON VALORES DEFINIDOS
			$campos = $this->datosDePruebaLoteAlta()[0];
			$sql_params = $this->datosDePruebaLoteAlta()[1]; 

			//GENERAMOS LA SENTENCIA SQL DE INSERCION
			$sql	= 'INSERT INTO lote('.implode(',', array_keys($campos)).') VALUES ('.implode(',', array_values($campos)).')';
			$test	= $cnx->consulta(Conexiones::INSERT, $sql, $sql_params);

			//SE SETEA EL ID QUE RECIBIÓ EL LOTE INSERTADO
			$this::$id_lote = $test;
			$this->assertIsNumeric(($test), 'Fallo en el alta de Lote');

		} catch(Exception $e) {
			$this->fail($e->getMessage());
		}
		return $cnx;
	}

	/**
	 * Test de MODIFICACION DE LOTE
	 *
	 * @param [type] $cnx
	 * @depends testAltaLote
	 * @return Conexiones
	 */
	public function testGestionarModificarLote(&$cnx){
		try {
			//TRAE LOS CAMPOS Y LOS DATOS CON VALORES DEFINIDOS
			$campos = $this->datosDePruebaLoteModificacion()[0];
			$sql_params = $this->datosDePruebaLoteModificacion()[1]; 

			//SETEAMOS DOS CAMPOS PARA PROBAR LA MODIFICACION
			$sql_params[":firmante_id"] = 31;
			$sql_params[":fecha_inicio_contrato"] = DateTime::createFromFormat('Y-m-d', '2021-08-24')->format('Y-m-d');
			
			//GENERAMOS LA SENTENCIA SQL DE MODIFICACION
			$sql	= 'UPDATE lote SET '.implode(',', $campos).' WHERE id = :id';
			$test	= $cnx->consulta(Conexiones::UPDATE, $sql, $sql_params);
			$this->assertEquals(1,($test), 'Fallo en el modificacion de Lote');

		} catch(Exception $e) {
			$this->fail($e->getMessage());
		}
		return $cnx;
	}

	/**
	 * Test de ALTA DE AGENTE EN LOTE AGREGADO
	 *
	 * @param [type] $cnx
	 * @depends testGestionarModificarLote
	 * @return Conexiones
	 */
	public function testAgregarAgenteLote(&$cnx){
		try {
			//SE INICIALIZA API SIGARHU
			$this->inicializarSigarhu();
			//SE OPTIENE AGENTE DESDE SIGARHU
			$agente = Modelo\SigarhuApi::getAgente(20367240203);
			//OBTENGO LOS DATOS PARA AGREGAR EL AGENTE
			$datos = $this->datosDePruebaAgenteAlta($agente);
			$campos = $datos[0];
			$sql_params = $datos[1];

			//GENERAMOS LA SENTENCIA SQL DE INSERCION
			$sql	= 'INSERT INTO lote_cuit('.implode(',', $campos).') VALUES (:'.implode(',:', $campos).')';
			$test	= $cnx->consulta(Conexiones::INSERT, $sql, $sql_params);
			$this->assertIsNumeric(($test), 'Fallo en el alta de Agente');
			$this::$id_lote_cuit = $test;
		} catch(Exception $e) {
			$this->fail($e->getMessage());
		}

		return $cnx;
	}

	/**
	 * Test de ALTA DE LEGAJO DE AGENTE AGREGADO
	 *
	 * @depends testAgregarAgenteLote
	 * @return void
	 */
	public function testAltaLegajoAgente(&$cnx){
		$cnx	= new Conexiones();
		try {
			
			//SE INICIALIZA API SIGARHU
			$this->inicializarSigarhu();
			//SE OPTIENE AGENTE DESDE SIGARHU
			Modelo\SigarhuApi::contiene(['situacion_escalafonaria','persona','dependencia', 'perfil_puesto', 'ubicacion','horario']);
			$agente = Modelo\SigarhuApi::getAgente(20367240203);
			if(empty($agente->cuit)) {
				$this->assertNotEmpty(($agente->cuit), 'El Agente con cuit 20367240203 no existe.');
			}

			//OBTENGO LOS DATOS PARA AGREGAR EL AGENTE
			$datos = $this->datosDePruebaLegajo($agente);
			$campos = $datos[0];
			$sql_params = $datos[1];

			//GENERAMOS LA SENTENCIA SQL DE INSERCION
			$sql	= 'INSERT INTO legajos('.implode(',', $campos).') VALUES (:'.implode(',:', $campos).')';
			$test	= $cnx->consulta(Conexiones::INSERT, $sql, $sql_params);

			$this->assertIsNumeric(($test), 'Fallo en el alta de Agente');

			$sql = "UPDATE lote_cuit SET id_legajo = $test WHERE id = {$this::$id_lote_cuit}";
			$test	= $cnx->consulta(Conexiones::UPDATE, $sql, []);

			$this->assertEquals(1,($test), 'No se pudo asociar el Legajo al Lote_Cuit');

		} catch(Exception $e) {
			$this->fail($e->getMessage());
		}

		$cnx->rollback();
	}

	/*=================
		Prueba de API Sigarhu
	===================*/

	public function testEndpointSigarhu(){
		try {
			$this->inicializarSigarhu();
			$test = Modelo\SigarhuApi::getAgente(20367240203);
			$this->assertObjectHasAttribute("id",($test), 'Fallo Sigarhu::getAgente');
		} catch(Exception $e) {
			$this->fail($e->getMessage());
		}
	}

	/*=================
		INICIALIZAR SIGARHU
	===================*/

	private function inicializarSigarhu(){
		//INSTANCIAMOS CONFIG PORQUE PARA CONECTARNOS A LA DB DEBEREMOS TENER DEFINIDA LA VARIABLE
		$config	= FMT\Configuracion::instancia();
		$config->cargar(BASE_PATH . '/config');
		Modelo\SigarhuApi::init($config['app']['endpoint_sigarhu'],['CURLOPT_SSL_VERIFYPEER' => $config['app']['ssl_verifypeer']]);
		Modelo\SigarhuApi::setToken($config['app']['modulo'], \FMT\Helper\Arr::get($config['app'],'sigarhu_access_token'));
	}

	/*=================
		DATOS DE PRUEBA
	===================*/


	private function datosDePruebaLoteAlta(){

		$campos	= [
			'id_tipo_lote'				=> ':tipo_lote_id',
			'id_dependencia'			=> ':dependencia_id',
			'id_contratante'			=> ':contratante_id',
			'id_firmante'				=> ':firmante_id',
			'id_modalidad_vinculacion'	=> ':modalidad_vinculacion_id',
			'id_situacion_revista'		=> ':situacion_revista_id',
			'fecha_inicio_contrato'		=> ':fecha_inicio_contrato',
			'fecha_apertura'			=> ':fecha_apertura',
			'estado'					=> ':estado',

		];

		$sql_params	= [
			':tipo_lote_id'				=> 1,
			':dependencia_id'			=> 6,
			':contratante_id'			=>  1701,
			':firmante_id'				=> 14,
			':modalidad_vinculacion_id'	=> 2,
			':situacion_revista_id'		=>  5,
			':fecha_inicio_contrato'	=> DateTime::createFromFormat('Y-m-d', '2021-08-18')->format('Y-m-d'),
			':fecha_apertura'			=> DateTime::createFromFormat('Y-m-d', '2021-08-18')->format('Y-m-d'),
			':estado'					=> ("sin_publicar"),
		];
		
		return array($campos,$sql_params);

	}

	private function datosDePruebaLoteModificacion(){

		$campos	= [
			'id_tipo_lote'				=> 'id_tipo_lote = :tipo_lote_id',
			'id_dependencia'			=> 'id_dependencia = :dependencia_id',
			'id_contratante'			=> 'id_contratante = :contratante_id',
			'id_firmante'				=> 'id_firmante = :firmante_id',
			'id_modalidad_vinculacion'	=> 'id_modalidad_vinculacion = :modalidad_vinculacion_id',
			'id_situacion_revista'		=> 'id_situacion_revista = :situacion_revista_id',
			'fecha_inicio_contrato'		=> 'fecha_inicio_contrato = :fecha_inicio_contrato',
			'fecha_apertura'			=> 'fecha_apertura = :fecha_apertura',
			'estado'					=> 'estado = :estado',
		];

			$sql_params	= [
			':id'				=> $this::$id_lote,
			':tipo_lote_id'				=> 1,
			':dependencia_id'			=> 6,
			':contratante_id'			=>  1701,
			':firmante_id'				=> 14,
			':modalidad_vinculacion_id'	=> 2,
			':situacion_revista_id'		=>  5,
			':fecha_inicio_contrato'	=> DateTime::createFromFormat('Y-m-d', '2021-08-18')->format('Y-m-d'),
			':fecha_apertura'			=> DateTime::createFromFormat('Y-m-d', '2021-08-18')->format('Y-m-d'),
			':estado'					=> ("sin_publicar"),
		];
		
		return array($campos,$sql_params);

	}

	private function datosDePruebaAgenteAlta($agente){

		$campos	= [
			'id_lote',
			'cuit',
			'fecha_fin_contrato',
			'proporcional',
			'nombre_apellido',
			'aprobacion_desarrollo',
			'aprobacion_control',
			'aprobacion_convenios',
		];
		$sql_params	= [
			':id_lote'					=> $this::$id_lote,
			':cuit'						=> $agente->cuit,
			':fecha_fin_contrato'		=>  DateTime::createFromFormat('Y-m-d', '2021-11-10')->format('Y-m-d'),
			':proporcional'				=> null,
			':nombre_apellido'			=> "{$agente->persona->nombre} {$agente->persona->apellido}",
			':aprobacion_desarrollo'	=> 1,
			':aprobacion_control'		=> null,
			':aprobacion_convenios'		=> 1,
		];

		return array($campos,$sql_params);
	}

	private function datosDePruebaLegajo($agente){

		$campos	= [
			'id_lote',
			'id_lote_cuit',
			'nombre',
			'apellido',
			'cuit',
			'tipo_documento',
			'documento',
			'nacionalidad',
			'fecha_nac',
			'email',
			'estado_civil',
			'genero',
			'id_provincia',
			'id_localidad',
			'calle',
			'numero',
			'piso',
			'depto',
			'id_tipo_titulo',
			'id_titulo',
			'id_estado_titulo',
			'denominacion_funcion',
			'objetivo_gral',
			'objetivo_especifico',
			'tarea',
			'resultado',
			'estandares',
			'id_agrupamiento'	,
			'id_nivel',
			'id_tramo',
			'id_grado',
			'compensacion_geografica',
			'compensacion_transitoria',
			'unidad_retributiva',
			'id_dependencia',
			'dedicacion_funcional',
			'horarios',
			'id_ubicacion',
		];

		$sql_params	= [
			':id_lote' => $this::$id_lote,
			':id_lote_cuit' => $this::$id_lote_cuit,
			':nombre' => $agente->persona->nombre,
			':apellido' => $agente->persona->apellido,
			':cuit' => $agente->cuit,
			':tipo_documento' => $agente->persona->tipo_documento,
			':documento' => $agente->persona->documento,
			':nacionalidad' => $agente->persona->nacionalidad,
			':fecha_nac' => DateTime::createFromFormat('Y-m-d', '1995-04-23')->format('Y-m-d'),
			':email' => $agente->persona->email,
			':estado_civil' => $agente->persona->estado_civil,
			':genero' => $agente->persona->genero,
			':id_provincia' => $agente->persona->domicilio->id_provincia,
			':id_localidad' => $agente->persona->domicilio->id_localidad,
			':calle' => $agente->persona->domicilio->calle,
			':numero' => $agente->persona->domicilio->numero,
			':piso' => $agente->persona->domicilio->piso,
			':depto' => $agente->persona->domicilio->depto,
			':id_tipo_titulo' => $agente->persona->titulos[0]->id_tipo_titulo,
			':id_titulo' => $agente->persona->titulos[0]->id_titulo,
			':id_estado_titulo' => $agente->persona->titulos[0]->id_estado_titulo,
			':denominacion_funcion' => $agente->perfil_puesto->denominacion_funcion,
			':objetivo_gral' => $agente->perfil_puesto->objetivo_gral,
			':objetivo_especifico' => $agente->perfil_puesto->objetivo_especifico,
			':estandares' => $agente->perfil_puesto->estandares,
			':tarea' => json_encode($agente->perfil_puesto->actividad),
			':resultado' => json_encode($agente->perfil_puesto->resultados_parciales_finales),
			':id_agrupamiento' => $agente->situacion_escalafonaria->id_agrupamiento	,
			':id_nivel' => $agente->situacion_escalafonaria->id_nivel,
			':id_tramo' => $agente->situacion_escalafonaria->id_tramo,
			':id_grado' => $agente->situacion_escalafonaria->id_grado,
			':compensacion_geografica' => $agente->situacion_escalafonaria->compensacion_geografica,
			':compensacion_transitoria' => $agente->situacion_escalafonaria->compensacion_transitoria,
			':unidad_retributiva' => $agente->situacion_escalafonaria->unidad_retributiva,
			':id_dependencia' => $agente->dependencia->id_dependencia,
			':dedicacion_funcional' => 100,
			':horarios' => json_encode($agente->horario->horarios),
			':id_ubicacion' => $agente->ubicacion->id
		];

		return array($campos,$sql_params);
	}

	

}