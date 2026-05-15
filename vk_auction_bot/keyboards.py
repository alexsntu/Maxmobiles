import json


def _cb(text: str, payload: dict, color: str = "default") -> dict:
    return {
        "action": {
            "type": "callback",
            "label": text,
            "payload": json.dumps(payload, ensure_ascii=False),
        },
        "color": color,
    }


def _inline(*rows: list[dict]) -> str:
    return json.dumps({"inline": True, "buttons": list(rows)}, ensure_ascii=False)


def confirm_lot_keyboard() -> str:
    return _inline([
        _cb("✅ Опубликовать", {"cmd": "lot_confirm", "v": "yes"}, "positive"),
        _cb("❌ Отменить",     {"cmd": "lot_confirm", "v": "no"},  "negative"),
    ])


def duration_keyboard() -> str:
    durations = [
        ("30 мин",  30),  ("1 час",    60),  ("2 часа",  120),
        ("3 часа",  180), ("6 часов",  360), ("12 часов", 720),
        ("24 часа", 1440),
    ]
    rows = []
    row: list[dict] = []
    for i, (label, value) in enumerate(durations):
        row.append(_cb(label, {"cmd": "duration", "v": value}))
        if len(row) == 3:
            rows.append(row)
            row = []
    if row:
        rows.append(row)
    rows.append([_cb("📅 Точная дата и время", {"cmd": "duration", "v": "datetime"})])
    return _inline(*rows)


def admin_lots_keyboard(lots: list[dict]) -> str:
    rows = [[_cb(f"#{lot['id']} {lot['title']} — {lot['current_price']:,} ₽".replace(",", " "),
                 {"cmd": "admin_lot", "id": lot["id"]})]
            for lot in lots]
    return _inline(*rows)


def admin_lot_actions_keyboard(lot_id: int) -> str:
    return _inline(
        [_cb("📊 Статистика",   {"cmd": "admin_stats",  "id": lot_id}),
         _cb("🚫 Отменить лот", {"cmd": "admin_cancel", "id": lot_id})],
        [_cb("◀️ Назад",        {"cmd": "admin_back",   "id": 0})],
    )
