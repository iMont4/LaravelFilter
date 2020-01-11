<?php

namespace Mont4\Filter;

use Illuminate\Database\Eloquent\Builder as QueryBuilder;

abstract class Filter
{
	const METHOD_LIKE  = 'like';
	const METHOD_EQUAL = 'equal';

	static $availableColumns = [];

	protected $availableSorts = [];

	protected $availableFilter = [];

	/**
	 * @var QueryBuilder
	 */
	private $query;
	/**
	 * @var \Illuminate\Http\Request
	 */
	protected $request;


	public function __construct(QueryBuilder $query)
	{
		$this->request = app('request');
		$this->query   = $query;
	}

	/**
	 * @param $method
	 * @param $args
	 *
	 * @return mixed
	 */
	public function __call($method, $args)
	{
		$resp = call_user_func_array([$this->query, $method], $args);

		// Only return $this if query builder is returned
		// We don't want to make actions to the builder unreachable
		return $resp instanceof QueryBuilder ? $this : $resp;
	}

	private function normalizeInput()
	{
		$fields = $this->request->input();
		
		if($this->request->input()){
            		return $this->excelexport();
        	     }

		$data = [
			'page'   => 1,
			'limit'  => 15,
			'order'  => [],
			'filter' => [],
		];
		foreach ($fields as $key => $value) {
			$fieldType = 'string'; // TODO.

			if ($key == 'filter_method_request')
				continue;
			if ($key == 'page')
				continue;
			if ($key == 'limit') {
				$data['limit'] = $value;
				continue;
			}

			// ------------------------------------ Order ------------------------------------
			if ($key == 'filter_order') {
				$value = json_decode($value, true);
				if (filled($value)) {
					$data['order'] = [
						'field'     => $value['column'],
						'direction' => $value['direction'] == 'ascending' ? 'asc' : 'desc',
					];
				}

				continue;
			}

			// ------------------------------------ Filter ------------------------------------
			if (!$value) {
				continue;
			}

			$method = self::METHOD_LIKE;
			if (str_contains($value, '~')) {
				$value = str_replace('~', '', $value);
//				$method = self::METHOD_LIKE;
			}

			$datum[] = [
				'field'  => $key,
				'type'   => $fieldType,
				'value'  => $value,
				'method' => $method,
			];

			$data['filter'] = $datum;
		}

		return $data;
	}

	public function handle()
	{
		$data = $this->normalizeInput();
		if (filled($data['order'])) {
			$this->query->orderBy($data['order']['field'], $data['order']['direction']);
		}else{
			$this->query->orderBy('created_at', 'desc');
		}

		foreach ($data['filter'] as $datum) {
			if (method_exists($this, $datum['field'])) {
				$method = $datum['field'];
				$this->$method($datum['value']);
			} else if ($datum['method'] == self::METHOD_LIKE) {
				$this->query->where($datum['field'], 'like', "%{$datum['value']}%");
			} else if ($datum['method'] == self::METHOD_EQUAL) {
				$this->query->where($datum['field'], $datum['value']);
			}
		}

		return $this->query->paginate($data['limit']);
	}
	
	// ------------------------------------ function for excel export ------------------------------------



    private function excelexport()
    {
        $arr = $this->query->orderBy('id', 'desc')->get()->toArray();

        // ------------------------------------ excel header for export ------------------------------------
        header("Content-Type:   application/vnd.ms-excel; charset=utf-8");
        header("Content-Disposition: attachment; filename=download.xls");

        // ------------------------------------ make data table ------------------------------------

        $flag = false;
        foreach($arr as $key => $data) {
            if(!$flag) {
                echo implode("\t", array_keys($data)) . "\r\n";
                $flag = true;
            }
            echo implode("\t", array_values($data)) . "\r\n";
        }
        exit();
    }

}
