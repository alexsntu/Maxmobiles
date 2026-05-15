from vkbottle.tools import BaseStateGroup


class NewLotStates(BaseStateGroup):
    waiting_photo = "waiting_photo"
    waiting_title = "waiting_title"
    waiting_description = "waiting_description"
    waiting_start_price = "waiting_start_price"
    waiting_min_step = "waiting_min_step"
    waiting_blitz_price = "waiting_blitz_price"
    waiting_rules = "waiting_rules"
    waiting_duration = "waiting_duration"
    waiting_end_datetime = "waiting_end_datetime"
    waiting_confirm = "waiting_confirm"


# In-memory state data store (keyed by peer_id / user VK ID)
_state_data: dict[int, dict] = {}


def get_data(peer_id: int) -> dict:
    return dict(_state_data.get(peer_id, {}))


def update_data(peer_id: int, **kwargs) -> None:
    _state_data.setdefault(peer_id, {}).update(kwargs)


def clear_data(peer_id: int) -> None:
    _state_data.pop(peer_id, None)
