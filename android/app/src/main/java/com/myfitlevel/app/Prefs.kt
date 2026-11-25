package com.myfitlevel.app

import android.content.Context

object Prefs {
    const val SITE = "https://myfitlevel.com"
    private fun sp(c: Context) = c.getSharedPreferences("mfl", Context.MODE_PRIVATE)
    fun key(c: Context): String = sp(c).getString("key", "") ?: ""
    fun setKey(c: Context, k: String) = sp(c).edit().putString("key", k.trim()).apply()
    fun status(c: Context): String = sp(c).getString("status", "Not synced yet.") ?: ""
    fun setStatus(c: Context, s: String) = sp(c).edit().putString("status", s).putLong("last", System.currentTimeMillis()).apply()
    fun lastSync(c: Context): Long = sp(c).getLong("last", 0L)
    fun widgetJson(c: Context): String = sp(c).getString("wjson", "") ?: ""
    fun widgetAt(c: Context): Long = sp(c).getLong("wat", 0L)
    fun setWidgetJson(c: Context, j: String) = sp(c).edit().putString("wjson", j).putLong("wat", System.currentTimeMillis()).apply()
    fun refreshing(c: Context): Boolean = System.currentTimeMillis() - sp(c).getLong("refreshing", 0L) < 90_000L
    fun setRefreshing(c: Context, on: Boolean) = sp(c).edit().putLong("refreshing", if (on) System.currentTimeMillis() else 0L).apply()
}
