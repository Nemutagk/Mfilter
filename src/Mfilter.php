<?php
namespace Nemutagk\Mfilter;

use Exception;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait Mfilter
{
	public function scopeAdvancedFilter($query) {
		if (Request::has('display'))
			$result = $this->process($query, Request::all())
				   ->orderBy(
					   	Request::input('order_col', 'id'),
					   	Request::input('order_dir','desc')
				   )
				   ->paginate(Request::input('limit', 20));
		else
			$result = $query->get();

		return $result;
	}

	public function process($query, $data) {
		$validator = Validator::make($data, [
			'order_col' => 'sometimes|required|in:'.$this->getOrdeableColumns()
			,'order_dir' => 'sometimes|required|in:asc,desc'
			,'limit' => 'sometimes|required|integer|min:1'
			,'filter_match' => 'sometimes|required|in:and,or'
			,'f' => 'sometimes|required|array'
			,'f.*.column' => 'required|in:'.$this->getWhiteListColumns()
			,'f.*.operator' => 'required|in:'.$this->getAllowedOperators()
			,'f.*.query_1' => 'required'
            ,'f.*.query_2' => 'required_if:f.*.operator,between,not_between'
		]);

		if ($validator->fails()) {
			throw new ValidationException($validator);
		}


		if (isset($data['f']))
			for($i=0; $i<count($data['f']); $i++) {
				$data['f'][$i]['query_1'] = is_numeric($data['f'][$i]['query_1']) ? intval($data['f'][$i]['query_1']) : $data['f'][$i]['query_1'];
				if (isset($data['f'][$i]['query_2']))
					$data['f'][$i]['query_2'] = is_numeric($data['f'][$i]['query_2']) ? intval($data['f'][$i]['query_2']) : $data['f'][$i]['query_2'];
			}

		if (isset($data['search'])) {
			$data['search'] = [
				'columns' => $this->getSearchColumns()
				,'search' => explode(',',$data['search'])
			];
		}


		return (new CustomQueryBuilder())->apply($query, $data);
	}

	protected function getWhiteListColumns() {
		return property_exists($this, 'allowedFilters') ? implode(',', $this->allowedFilters) : '';
	}

	protected function getOrdeableColumns() {
		return implode(',', $this->ordeable);
	}

	protected function getSearchColumns() {
		if (!property_exists($this, 'search'))
			throw new Exception("Tienes que definir las columnas a buscar", 1);

		return $this->search;
	}

	protected function getAllowedOperators() {
		return implode(',', [
            'equal_to',
            'not_equal_to',
            'less_than',
            'greater_than',
            'between',
            'not_between',
            'contains',
            'starts_with',
            'ends_with',
            'in_the_past',
            'in_the_next',
            'in_the_peroid',
            'less_than_count',
            'greater_than_count',
            'equal_to_count',
            'not_equal_to_count'
        ]);
	}
}