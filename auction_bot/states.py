from aiogram.fsm.state import State, StatesGroup


class NewLotStates(StatesGroup):
    """FSM states for the lot creation wizard."""
    waiting_photo       = State()
    waiting_title       = State()
    waiting_description = State()
    waiting_start_price = State()
    waiting_min_step    = State()
    waiting_bid_variants = State()  # 1 или 3 — количество кнопок-вариантов ставки
    waiting_blitz_price = State()
    waiting_rules       = State()
    waiting_duration    = State()
    waiting_confirm     = State()


class BidStates(StatesGroup):
    """FSM state when a user clicks the 'Make Bid' button and enters amount in PM."""
    waiting_amount = State()
