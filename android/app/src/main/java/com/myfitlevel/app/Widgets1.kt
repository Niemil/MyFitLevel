package com.myfitlevel.app

import android.content.Context
import android.view.View
import android.widget.RemoteViews
import org.json.JSONObject

class LevelWidget : BaseWidget() {
    override val layout = R.layout.w_level
    override fun render(ctx: Context, v: RemoteViews, d: JSONObject, s: Style, id: Int) {
        val lvl = d.optInt("level")
        v.setImageViewBitmap(R.id.l_ring, Gfx.ring(d.optInt("level_pct"), s.acc, s.track))
        v.txt(R.id.l_lvl, "$lvl", s.text)
        v.txt(R.id.l_name, d.optString("name"), s.text)
        v.txt(R.id.l_xp, "Level $lvl · ${Fmt.n(d.optInt("xp"))} points", s.sub)
        v.txt(R.id.l_next, "${Fmt.n(d.optInt("level_need") - d.optInt("level_xp"))} pts to level ${lvl + 1}", s.sub)
        v.setOnClickPendingIntent(R.id.w_content, open(ctx, id, 1, "/levels"))
    }
}

class StepsWidget : BaseWidget() {
    override val layout = R.layout.w_steps
    override fun render(ctx: Context, v: RemoteViews, d: JSONObject, s: Style, id: Int) {
        val steps = d.optInt("steps_today")
        val goal = maxOf(1000, d.optInt("steps_goal", 10000))
        v.txt(R.id.s_title, "👟 Steps today", s.sub)
        v.txt(R.id.s_big, Fmt.n(steps), s.text)
        v.setImageViewBitmap(R.id.s_bar, Gfx.bar(steps * 100 / goal, s.acc, s.track))
        v.txt(R.id.s_sub, "${steps * 100 / goal}% of ${Fmt.n(goal)}", s.text)
        v.txt(R.id.s_yest, "Yesterday ${Fmt.n(d.optInt("steps_yesterday"))}", s.sub)
        v.txt(R.id.s_stat, stepStatus(ctx), s.sub)
        v.setOnClickPendingIntent(R.id.w_content, open(ctx, id, 1, "/steps"))
    }
}

class BossWidget : BaseWidget() {
    override val layout = R.layout.w_boss
    override val defaultStyle = Style(6, 7, 94, 2, 0, 0)
    override fun render(ctx: Context, v: RemoteViews, d: JSONObject, s: Style, id: Int) {
        val b = (if (s.boss == 1) d.optJSONObject("raid") else d.optJSONObject("boss")) ?: d.optJSONObject("boss")
        if (b == null) {
            v.txt(R.id.b_emoji, "🏆", s.text); v.txt(R.id.b_name, "No boss right now", s.text); v.txt(R.id.b_kind, "A new one arrives soon", s.sub)
            v.setImageViewBitmap(R.id.b_bar, Gfx.bar(0, s.acc, s.track)); v.txt(R.id.b_hp, "", s.text); v.txt(R.id.b_meta, "", s.sub)
        } else {
            val pct = b.optInt("pct")
            v.txt(R.id.b_emoji, b.optString("emoji"), s.text)
            v.txt(R.id.b_name, b.optString("name"), s.text)
            v.txt(R.id.b_kind, (if (b.optBoolean("raid")) "Team raid" else "Weekly boss") + " · Tier ${b.optInt("tier")}", s.sub)
            v.setImageViewBitmap(R.id.b_bar, Gfx.bar(pct, if (pct <= 25) 0xFFFF5C5C.toInt() else s.acc, s.track))
            v.txt(R.id.b_hp, "${Fmt.n(b.optInt("hp"))} / ${Fmt.n(b.optInt("max_hp"))} HP", s.text)
            v.txt(R.id.b_meta, "⏳ ${b.optString("left")} left · your damage ${Fmt.n(b.optInt("my_damage"))}", s.sub)
        }
        v.setOnClickPendingIntent(R.id.w_content, open(ctx, id, 1, "/boss"))
    }
}

class WeekWidget : BaseWidget() {
    override val layout = R.layout.w_week
    override fun render(ctx: Context, v: RemoteViews, d: JSONObject, s: Style, id: Int) {
        val days = d.optInt("week_days"); val goal = maxOf(1, d.optInt("week_goal", 3))
        v.txt(R.id.k_title, "THIS WEEK", s.sub)
        v.txt(R.id.k_main, "$days / $goal days", s.text)
        v.setImageViewBitmap(R.id.k_bar, Gfx.bar(days * 100 / goal, s.acc, s.track))
        v.txt(R.id.k_sub, "🔥 ${d.optInt("streak")}-day streak · ${Fmt.n(d.optInt("week_points"))} pts", s.sub)
        v.setOnClickPendingIntent(R.id.w_content, open(ctx, id, 1, "/track"))
    }
}

class ChestWidget : BaseWidget() {
    override val layout = R.layout.w_chest
    override val defaultStyle = Style(6, 7, 94, 2, 0, 0)
    override fun render(ctx: Context, v: RemoteViews, d: JSONObject, s: Style, id: Int) {
        val ready = d.optBoolean("chest_ready")
        v.txt(R.id.c_emoji, if (ready) "🎁" else "✅", s.text)
        v.txt(R.id.c_title, if (ready) "Daily chest is ready!" else "Chest opened today", if (ready) s.acc else s.text)
        v.txt(R.id.c_sub, if (ready) "Tap to open: +${d.optInt("chest_reward")} coins" else "Come back tomorrow · streak ${d.optInt("chest_streak")}", s.sub)
        v.txt(R.id.c_coins, "🪙 ${Fmt.n(d.optInt("coins"))}", s.text)
        v.setOnClickPendingIntent(R.id.w_content, open(ctx, id, 1, "/feed"))
    }
}

class ActionsWidget : BaseWidget() {
    override val layout = R.layout.w_actions
    override val tools = false
    override fun render(ctx: Context, v: RemoteViews, d: JSONObject, s: Style, id: Int) {
        val items = listOf(Triple(R.id.a_log, "/track", 1), Triple(R.id.a_feed, "/feed", 2), Triple(R.id.a_boss, "/boss", 3), Triple(R.id.a_steps, "/steps", 4), Triple(R.id.a_chest, "/feed", 5))
        for ((rid, path, slot) in items) {
            v.setOnClickPendingIntent(rid, open(ctx, id, slot, path))
        }
        v.txt(R.id.a_log_t, "Log", s.text); v.txt(R.id.a_feed_t, "Feed", s.text); v.txt(R.id.a_boss_t, "Boss", s.text)
        v.txt(R.id.a_steps_t, "Steps", s.text); v.txt(R.id.a_chest_t, if (d.optBoolean("chest_ready")) "Chest!" else "Chest", if (d.optBoolean("chest_ready")) s.acc else s.text)
        v.txt(R.id.a_chest_e, if (d.optBoolean("chest_ready")) "🎁" else "✅", s.text)
    }
}

class LogWidget : BaseWidget() {
    override val layout = R.layout.w_log
    override val tools = false
    override val defaultStyle = Style(6, 7, 100, 2, 0, 0)
    override fun render(ctx: Context, v: RemoteViews, d: JSONObject, s: Style, id: Int) {
        v.txt(R.id.g_label, "Log workout", s.text)
        v.setOnClickPendingIntent(R.id.w_content, open(ctx, id, 1, "/track"))
    }
}

class FeedWidget : BaseWidget() {
    override val layout = R.layout.w_feed
    override fun render(ctx: Context, v: RemoteViews, d: JSONObject, s: Style, id: Int) = renderFeed(ctx, v, d, s, id, 5, true)

    protected fun renderFeed(ctx: Context, v: RemoteViews, d: JSONObject, s: Style, id: Int, rows: Int, title: Boolean) {
        val mine = s.feed == 1
        val arr = d.optJSONArray(if (mine) "feed_mine" else "feed_all")
        val ids = intArrayOf(R.id.f1, R.id.f2, R.id.f3, R.id.f4, R.id.f5)
        v.txt(R.id.f_title, if (mine) "📰 Feed · only you" else "📰 Feed · everyone", s.acc)
        for (i in 0 until 5) {
            if (i >= rows) { v.setViewVisibility(ids[i], View.GONE); continue }
            val o = arr?.optJSONObject(i)
            if (o == null) {
                v.setViewVisibility(ids[i], if (i == 0) View.VISIBLE else View.GONE)
                if (i == 0) v.txt(ids[i], "Nothing yet. Log a workout!", s.sub)
            } else {
                v.setViewVisibility(ids[i], View.VISIBLE)
                v.setTextViewText(ids[i], feedLine(o.optString("who"), o.optString("text"), Fmt.ago(o.optString("at"), d.optString("now"))))
                v.setTextColor(ids[i], s.text)
            }
        }
        v.setOnClickPendingIntent(R.id.w_content, open(ctx, id, 1, "/feed"))
    }
}

class FeedSmallWidget : BaseWidget() {
    override val layout = R.layout.w_feed_small
    override fun render(ctx: Context, v: RemoteViews, d: JSONObject, s: Style, id: Int) {
        val arr = d.optJSONArray(if (s.feed == 1) "feed_mine" else "feed_all")
        val ids = intArrayOf(R.id.f1, R.id.f2)
        v.txt(R.id.f_title, if (s.feed == 1) "📰 Feed · only you" else "📰 Feed", s.acc)
        for (i in 0 until 2) {
            val o = arr?.optJSONObject(i)
            if (o == null) { v.setViewVisibility(ids[i], View.GONE); continue }
            v.setViewVisibility(ids[i], View.VISIBLE)
            v.setTextViewText(ids[i], feedLine(o.optString("who"), o.optString("text"), Fmt.ago(o.optString("at"), d.optString("now"))))
            v.setTextColor(ids[i], s.text)
        }
        v.setOnClickPendingIntent(R.id.w_content, open(ctx, id, 1, "/feed"))
    }
}

class MiniWidget : BaseWidget() {
    override val layout = R.layout.w_mini
    override val defaultStyle = Style(5, 0, 40, 1, 0, 0)
    override fun render(ctx: Context, v: RemoteViews, d: JSONObject, s: Style, id: Int) {
        v.txt(R.id.m1_v, "${d.optInt("level")}", s.acc); v.txt(R.id.m1_l, "level", s.sub)
        v.txt(R.id.m2_v, Fmt.k(d.optInt("steps_today")), s.text); v.txt(R.id.m2_l, "steps", s.sub)
        v.txt(R.id.m3_v, "${d.optInt("streak")}", s.text); v.txt(R.id.m3_l, "day streak", s.sub)
        v.setOnClickPendingIntent(R.id.m1, open(ctx, id, 1, "/levels"))
        v.setOnClickPendingIntent(R.id.m2, open(ctx, id, 2, "/steps"))
        v.setOnClickPendingIntent(R.id.m3, open(ctx, id, 3, "/track"))
    }
}

class BundleWidget : BaseWidget() {
    override val layout = R.layout.w_bundle
    override fun render(ctx: Context, v: RemoteViews, d: JSONObject, s: Style, id: Int) {
        val lvl = d.optInt("level")
        v.txt(R.id.n_name, d.optString("name"), s.text)
        v.txt(R.id.n_xp, "${Fmt.n(d.optInt("xp"))} points · ${Fmt.n(d.optInt("level_need") - d.optInt("level_xp"))} to next", s.sub)
        v.txt(R.id.n_lvl, "L$lvl", s.acc)
        v.setImageViewBitmap(R.id.n_bar, Gfx.bar(d.optInt("level_pct"), s.acc, s.track))
        val steps = d.optInt("steps_today"); val goal = maxOf(1000, d.optInt("steps_goal", 10000))
        v.txt(R.id.n_s_t, "👟 STEPS", s.sub); v.txt(R.id.n_s_big, Fmt.n(steps), s.text)
        v.setImageViewBitmap(R.id.n_s_bar, Gfx.bar(steps * 100 / goal, s.acc, s.track))
        v.txt(R.id.n_s_sub, "of ${Fmt.n(goal)}", s.sub)
        val b = (if (s.boss == 1) d.optJSONObject("raid") else d.optJSONObject("boss")) ?: d.optJSONObject("boss")
        if (b != null) {
            val pct = b.optInt("pct")
            v.txt(R.id.n_b_t, b.optString("emoji") + " " + b.optString("name"), s.sub)
            v.txt(R.id.n_b_big, "$pct%", s.text)
            v.setImageViewBitmap(R.id.n_b_bar, Gfx.bar(pct, if (pct <= 25) 0xFFFF5C5C.toInt() else s.acc, s.track))
            v.txt(R.id.n_b_sub, "${b.optString("left")} left · you ${Fmt.k(b.optInt("my_damage"))}", s.sub)
        } else {
            v.txt(R.id.n_b_t, "🏆 Boss", s.sub); v.txt(R.id.n_b_big, "–", s.text); v.setImageViewBitmap(R.id.n_b_bar, Gfx.bar(0, s.acc, s.track)); v.txt(R.id.n_b_sub, "next one soon", s.sub)
        }
        val goalW = maxOf(1, d.optInt("week_goal", 3))
        v.txt(R.id.n_w_t, "🔥 WEEK", s.sub); v.txt(R.id.n_w_big, "${d.optInt("week_days")} / $goalW", s.text); v.txt(R.id.n_w_sub, "${d.optInt("streak")}-day streak", s.sub)
        val ready = d.optBoolean("chest_ready")
        v.txt(R.id.n_c_t, "🎁 CHEST", s.sub); v.txt(R.id.n_c_big, if (ready) "Ready!" else "Opened", if (ready) s.acc else s.text); v.txt(R.id.n_c_sub, "🪙 ${Fmt.n(d.optInt("coins"))}", s.sub)
        for ((rid, slot, path) in listOf(Triple(R.id.n_steps, 1, "/steps"), Triple(R.id.n_boss, 2, "/boss"), Triple(R.id.n_week, 3, "/track"), Triple(R.id.n_chest, 4, "/feed"), Triple(R.id.n_head, 5, "/levels")))
            v.setOnClickPendingIntent(rid, open(ctx, id, slot, path))
        for ((rid, slot, path) in listOf(Triple(R.id.n_log, 6, "/track"), Triple(R.id.n_feed, 7, "/feed"), Triple(R.id.n_bossb, 8, "/boss"), Triple(R.id.n_stepsb, 9, "/steps"))) {
            v.setOnClickPendingIntent(rid, open(ctx, id, slot, path))
        }
        for (cid in listOf(R.id.n_log_bg, R.id.n_feed_bg, R.id.n_bossb_bg, R.id.n_stepsb_bg)) {
            v.setInt(cid, "setColorFilter", s.acc); v.setInt(cid, "setImageAlpha", 70)
        }
        for (tid in listOf(R.id.n_log_t, R.id.n_feed_t, R.id.n_bossb_t, R.id.n_stepsb_t)) v.setTextColor(tid, s.text)
    }
}
