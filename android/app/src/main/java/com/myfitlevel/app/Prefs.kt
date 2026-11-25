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
}
