package com.myfitlevel.app

import android.content.Context
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.text.SimpleDateFormat
import java.util.Locale

object WidgetData {
    fun cached(c: Context): JSONObject? {
        val j = Prefs.widgetJson(c)
        return if (j.isEmpty()) null else try { JSONObject(j) } catch (e: Exception) { null }
    }

    /** Downloads the numbers the widgets show. Returns true on success. */
    suspend fun fetch(c: Context): Boolean = withContext(Dispatchers.IO) {
        val key = Prefs.key(c)
        if (key.length < 20) return@withContext false
        val con = URL(Prefs.SITE + "/api/v1/widgets").openConnection() as HttpURLConnection
        try {
            con.connectTimeout = 15000
            con.readTimeout = 20000
            con.setRequestProperty("Authorization", "Bearer $key")
            if (con.responseCode != 200) return@withContext false
            val body = con.inputStream.bufferedReader().use { it.readText() }
            JSONObject(body)
            Prefs.setWidgetJson(c, body)
            true
        } catch (e: Exception) {
            false
        } finally {
            con.disconnect()
        }
    }
}

object Fmt {
    fun n(v: Int): String = String.format(Locale.US, "%,d", v)
    fun k(v: Int): String = when {
        v >= 1_000_000 -> String.format(Locale.US, "%.1fM", v / 1_000_000.0).replace(".0M", "M")
        v >= 10_000 -> String.format(Locale.US, "%.1fK", v / 1000.0).replace(".0K", "K")
        else -> n(v)
    }

    /** Short "5m / 3h / 2d" from two server timestamps (both in the same zone, so the zone does not matter). */
    fun ago(at: String, now: String): String {
        return try {
            val f = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US)
            val d = (f.parse(now)!!.time - f.parse(at)!!.time) / 60000
            when {
                d < 1 -> "now"
                d < 60 -> "${d}m"
                d < 1440 -> "${d / 60}h"
                else -> "${d / 1440}d"
            }
        } catch (e: Exception) {
            ""
        }
    }
}
