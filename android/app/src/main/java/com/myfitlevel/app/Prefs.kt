package com.myfitlevel.app

import android.content.Context

object Prefs {
    const val SITE = "https://myfitlevel.com"
    private fun sp(c: Context) = c.getSharedPreferences("mfl", Context.MODE_PRIVATE)
    fun key(c: Context): String = sp(c).getString("key", "") ?: ""
    fun setKey(c: Context, k: String) = sp(c).edit().putString("key", k.trim()).apply()
    fun status(c: Context): String = sp(c).getString("status", "Not synced yet.") ?: ""
    fun setStatus(c: Context, s: String) {
        val e = sp(c).edit().putString("status", s).putLong("last", System.currentTimeMillis())
        if (s.startsWith("Synced") || s.startsWith("No steps found")) e.putLong("ok", System.currentTimeMillis())
        e.apply()
    }
    fun lastOk(c: Context): Long = sp(c).getLong("ok", 0L)
    fun ago(t: Long): String {
        if (t <= 0L) return "never"
        val m = (System.currentTimeMillis() - t) / 60000
        return when { m < 1 -> "just now"; m < 60 -> "$m min ago"; m < 48 * 60 -> "${m / 60} h ago"; else -> "${m / 1440} days ago" }
    }
    /** Short line for the floating chip. */
    fun chip(c: Context): String = when {
        key(c).isEmpty() -> "\u26A0 Steps: not linked"
        lastOk(c) == 0L -> "\u26A0 Steps: not synced yet"
        System.currentTimeMillis() - lastOk(c) > 3 * 3600_000L -> "\u26A0 Steps synced ${ago(lastOk(c))}"
        else -> "\u2713 Steps synced ${ago(lastOk(c))}"
    }
    fun lastSync(c: Context): Long = sp(c).getLong("last", 0L)
    fun asked(c: Context, k: String): Boolean = sp(c).getBoolean("asked_$k", false)
    fun setAsked(c: Context, k: String) = sp(c).edit().putBoolean("asked_$k", true).apply()
    fun widgetJson(c: Context): String = sp(c).getString("wjson", "") ?: ""
    fun widgetAt(c: Context): Long = sp(c).getLong("wat", 0L)
    fun setWidgetJson(c: Context, j: String) = sp(c).edit().putString("wjson", j).putLong("wat", System.currentTimeMillis()).apply()
    fun refreshing(c: Context): Boolean = System.currentTimeMillis() - sp(c).getLong("refreshing", 0L) < 90_000L
    fun setRefreshing(c: Context, on: Boolean) = sp(c).edit().putLong("refreshing", if (on) System.currentTimeMillis() else 0L).apply()
}
