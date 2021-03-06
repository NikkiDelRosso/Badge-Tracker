<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Carbon\Carbon;

class Season extends Model
{
	protected $dates = ['start_date', 'end_date'],
		$guarded = ['id'];

	function badges() {
		return $this->hasMany('App\Models\Badge');
	}

	// We could give this an actual name field, but id works for me for now
	function __toString() {
		return $this->name;
	}

	static function currentSeason() {
		$current = self::where('is_current', true)->first();

		if (!is_null($current)) {
			return $current;
		}

		$new_current = self::whereDate('start_date', '<=', Carbon::now())
			->whereHas('badges')
			->orderBy('start_date', 'DESC')
			->first();

		if (!is_null($new_current)) {
			$new_current->setCurrent();
			return $new_current;
		}

		return null;
	}

	static function unsetCurrentSeason($notSeasonID = null)
	{
		$query = Season::where('is_current', true);
		if (!is_null($notSeasonID)) {
			$query->where('id', '!=', $notSeasonID);
		}

		with(clone $query)->whereNull('end_date')->update(['end_date' => Carbon::now()]);
		$query->update(['is_current' => false]);

		Challenger::whereNotNull('current_season_badges')->update(['current_season_badges' => null]);
		Challenger::whereNotNull('current_season_type_points')->update(['current_season_type_points' => null]);
	}

	function setCurrent() {
		if ($this->badges()->count() < 1) {
			return false;
		}

		Season::unsetCurrentSeason($this->id);
		$this->is_current = true;
		$this->save();
		return true;
	}
}
