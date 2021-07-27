<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Status;
//use App\Transformer\Api\v3\StatusTransformer;
use App\Transformer\Api\StatusStatelessTransformer;
use App\Transformer\Api\StatusTransformer;
use League\Fractal;
use League\Fractal\Serializer\ArraySerializer;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;

class StatusService {

	const CACHE_KEY = 'pf:services:status:';

	public static function key($id)
	{
		return self::CACHE_KEY . $id;
	}

	public static function get($id, $publicOnly = true)
	{
		return Cache::remember(self::key($id), now()->addDays(7), function() use($id, $publicOnly) {
			if($publicOnly) {
				$status = Status::whereScope('public')->find($id);
			} else {
				$status = Status::whereIn('scope', ['public', 'private', 'unlisted'])->find($id);
			}
			if(!$status) {
				return null;
			}
			$fractal = new Fractal\Manager();
			$fractal->setSerializer(new ArraySerializer());
			$resource = new Fractal\Resource\Item($status, new StatusStatelessTransformer());
			return $fractal->createData($resource)->toArray();
		});
	}

	public static function del($id)
	{
		$status = self::get($id);
		if($status && isset($status['account']) && isset($status['account']['id'])) {
			Cache::forget('profile:embed:' . $status['account']['id']);
		}
		Cache::forget('status:thumb:nsfw0' . $id);
		Cache::forget('status:thumb:nsfw1' . $id);
		Cache::forget('pf:services:sh:id:' . $id);
		Cache::forget('status:transformer:media:attachments:' . $id);
		PublicTimelineService::rem($id);
		return Cache::forget(self::key($id));
	}
}
