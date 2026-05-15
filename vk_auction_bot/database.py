import aiosqlite
from datetime import datetime
from typing import Optional

DB_PATH = "auction_vk.db"


async def init_db() -> None:
    async with aiosqlite.connect(DB_PATH) as db:
        await db.executescript("""
            CREATE TABLE IF NOT EXISTS users (
                vk_id      INTEGER PRIMARY KEY,
                full_name  TEXT,
                created_at TEXT DEFAULT (datetime('now'))
            );

            CREATE TABLE IF NOT EXISTS lots (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                title         TEXT    NOT NULL,
                description   TEXT,
                photo_att     TEXT    NOT NULL,
                start_price   INTEGER NOT NULL,
                min_step      INTEGER NOT NULL,
                blitz_price   INTEGER,
                current_price INTEGER NOT NULL,
                winner_id     INTEGER,
                status        TEXT    NOT NULL DEFAULT 'active',
                end_time      TEXT    NOT NULL,
                wall_post_id  INTEGER,
                created_by    INTEGER NOT NULL,
                rules         TEXT,
                created_at    TEXT    DEFAULT (datetime('now')),
                FOREIGN KEY (winner_id)  REFERENCES users(vk_id),
                FOREIGN KEY (created_by) REFERENCES users(vk_id)
            );

            CREATE TABLE IF NOT EXISTS bids (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                lot_id     INTEGER NOT NULL,
                user_id    INTEGER NOT NULL,
                amount     INTEGER NOT NULL,
                created_at TEXT    DEFAULT (datetime('now')),
                FOREIGN KEY (lot_id)  REFERENCES lots(id),
                FOREIGN KEY (user_id) REFERENCES users(vk_id)
            );
        """)
        await db.commit()


# ─── Users ────────────────────────────────────────────────────────────────────

async def upsert_user(vk_id: int, full_name: str) -> None:
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute(
            """
            INSERT INTO users (vk_id, full_name)
            VALUES (?, ?)
            ON CONFLICT(vk_id) DO UPDATE SET full_name = excluded.full_name
            """,
            (vk_id, full_name),
        )
        await db.commit()


async def get_user(vk_id: int) -> Optional[dict]:
    async with aiosqlite.connect(DB_PATH) as db:
        db.row_factory = aiosqlite.Row
        async with db.execute("SELECT * FROM users WHERE vk_id = ?", (vk_id,)) as cur:
            row = await cur.fetchone()
            return dict(row) if row else None


# ─── Lots ─────────────────────────────────────────────────────────────────────

async def create_lot(
    title: str,
    description: str,
    photo_att: str,
    start_price: int,
    min_step: int,
    end_time: datetime,
    created_by: int,
    blitz_price: Optional[int] = None,
    rules: Optional[str] = None,
) -> int:
    async with aiosqlite.connect(DB_PATH) as db:
        cur = await db.execute(
            """
            INSERT INTO lots
                (title, description, photo_att, start_price, min_step, blitz_price,
                 current_price, end_time, created_by, rules)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            """,
            (title, description, photo_att, start_price, min_step, blitz_price,
             start_price, end_time.isoformat(), created_by, rules),
        )
        await db.commit()
        return cur.lastrowid


async def get_lot(lot_id: int) -> Optional[dict]:
    async with aiosqlite.connect(DB_PATH) as db:
        db.row_factory = aiosqlite.Row
        async with db.execute("SELECT * FROM lots WHERE id = ?", (lot_id,)) as cur:
            row = await cur.fetchone()
            return dict(row) if row else None


async def get_active_lots() -> list[dict]:
    async with aiosqlite.connect(DB_PATH) as db:
        db.row_factory = aiosqlite.Row
        async with db.execute(
            "SELECT * FROM lots WHERE status = 'active' ORDER BY end_time"
        ) as cur:
            rows = await cur.fetchall()
            return [dict(r) for r in rows]


async def set_lot_wall_post_id(lot_id: int, wall_post_id: int) -> None:
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute(
            "UPDATE lots SET wall_post_id = ? WHERE id = ?",
            (wall_post_id, lot_id),
        )
        await db.commit()


async def update_lot_bid(lot_id: int, new_price: int, winner_id: int) -> None:
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute(
            "UPDATE lots SET current_price = ?, winner_id = ? WHERE id = ?",
            (new_price, winner_id, lot_id),
        )
        await db.commit()


async def extend_lot_time(lot_id: int, new_end_time: datetime) -> None:
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute(
            "UPDATE lots SET end_time = ? WHERE id = ?",
            (new_end_time.isoformat(), lot_id),
        )
        await db.commit()


async def finish_lot(lot_id: int, winner_id: Optional[int]) -> None:
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute(
            "UPDATE lots SET status = 'finished', winner_id = ? WHERE id = ?",
            (winner_id, lot_id),
        )
        await db.commit()


async def cancel_lot(lot_id: int) -> None:
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute(
            "UPDATE lots SET status = 'cancelled' WHERE id = ?",
            (lot_id,),
        )
        await db.commit()


# ─── Bids ─────────────────────────────────────────────────────────────────────

async def count_bids(lot_id: int) -> int:
    async with aiosqlite.connect(DB_PATH) as db:
        async with db.execute(
            "SELECT COUNT(*) FROM bids WHERE lot_id = ?", (lot_id,)
        ) as cur:
            row = await cur.fetchone()
            return row[0] if row else 0


async def add_bid(lot_id: int, user_id: int, amount: int) -> None:
    async with aiosqlite.connect(DB_PATH) as db:
        await db.execute(
            "INSERT INTO bids (lot_id, user_id, amount) VALUES (?, ?, ?)",
            (lot_id, user_id, amount),
        )
        await db.commit()


async def get_lot_bids(lot_id: int) -> list[dict]:
    """Return bids sorted by amount DESC (highest first)."""
    async with aiosqlite.connect(DB_PATH) as db:
        db.row_factory = aiosqlite.Row
        async with db.execute(
            """
            SELECT b.*, u.full_name
            FROM bids b
            LEFT JOIN users u ON b.user_id = u.vk_id
            WHERE b.lot_id = ?
            ORDER BY b.amount DESC
            """,
            (lot_id,),
        ) as cur:
            rows = await cur.fetchall()
            return [dict(r) for r in rows]


async def get_lot_bids_chrono(lot_id: int) -> list[dict]:
    async with aiosqlite.connect(DB_PATH) as db:
        db.row_factory = aiosqlite.Row
        async with db.execute(
            """
            SELECT b.*, u.full_name
            FROM bids b
            LEFT JOIN users u ON b.user_id = u.vk_id
            WHERE b.lot_id = ?
            ORDER BY b.created_at ASC
            """,
            (lot_id,),
        ) as cur:
            rows = await cur.fetchall()
            return [dict(r) for r in rows]


async def get_unique_bidder_count(lot_id: int) -> int:
    async with aiosqlite.connect(DB_PATH) as db:
        async with db.execute(
            "SELECT COUNT(DISTINCT user_id) FROM bids WHERE lot_id = ?", (lot_id,)
        ) as cur:
            row = await cur.fetchone()
            return row[0] if row else 0


async def get_user_bid_for_lot(lot_id: int, user_id: int) -> Optional[dict]:
    async with aiosqlite.connect(DB_PATH) as db:
        db.row_factory = aiosqlite.Row
        async with db.execute(
            "SELECT * FROM bids WHERE lot_id = ? AND user_id = ? ORDER BY amount DESC LIMIT 1",
            (lot_id, user_id),
        ) as cur:
            row = await cur.fetchone()
            return dict(row) if row else None


async def cancel_top_bid(lot_id: int, user_id: int) -> bool:
    """Remove the user's top bid and restore price to the previous leader. Returns True on success."""
    async with aiosqlite.connect(DB_PATH) as db:
        async with db.execute(
            "SELECT id FROM bids WHERE lot_id = ? AND user_id = ? ORDER BY amount DESC LIMIT 1",
            (lot_id, user_id),
        ) as cur:
            bid_row = await cur.fetchone()

        if not bid_row:
            return False

        await db.execute("DELETE FROM bids WHERE id = ?", (bid_row[0],))

        async with db.execute(
            "SELECT user_id, amount FROM bids WHERE lot_id = ? ORDER BY amount DESC LIMIT 1",
            (lot_id,),
        ) as cur:
            top_row = await cur.fetchone()

        if top_row:
            new_winner_id, new_price = top_row[0], top_row[1]
        else:
            async with db.execute(
                "SELECT start_price FROM lots WHERE id = ?", (lot_id,)
            ) as cur:
                lot_row = await cur.fetchone()
            new_price = lot_row[0] if lot_row else 0
            new_winner_id = None

        await db.execute(
            "UPDATE lots SET current_price = ?, winner_id = ? WHERE id = ?",
            (new_price, new_winner_id, lot_id),
        )
        await db.commit()
        return True
