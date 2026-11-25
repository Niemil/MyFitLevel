package com.myfitlevel.app

import android.app.Activity
import android.app.AlertDialog
import android.app.DownloadManager
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.net.Uri
import android.os.Environment
import android.provider.Settings
import android.widget.Toast
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import org.json.JSONObject
import java.io.File
import java.net.HttpURLConnection
import java.net.URL
import kotlin.concurrent.thread

/** Looks for a newer build on GitHub and offers a one-tap update. */
object Updater {
    private const val API = "https://api.github.com/repos/Niemil/MyFitLevel/releases/tags/latest"

    private fun installedCode(c: Context): Long {
        val i = c.packageManager.getPackageInfo(c.packageName, 0)
        return if (android.os.Build.VERSION.SDK_INT >= 28) i.longVersionCode else @Suppress("DEPRECATION") i.versionCode.toLong()
    }

    fun check(a: Activity, force: Boolean = false) {
        val sp = a.getSharedPreferences("mfl", Context.MODE_PRIVATE)
        if (!force && System.currentTimeMillis() - sp.getLong("upd_check", 0L) < 6 * 3600_000L) return
        sp.edit().putLong("upd_check", System.currentTimeMillis()).apply()
        thread {
            try {
                val con = URL(API).openConnection() as HttpURLConnection
                con.connectTimeout = 10000; con.readTimeout = 10000
                con.setRequestProperty("Accept", "application/vnd.github+json")
                if (con.responseCode != 200) return@thread
                val j = JSONObject(con.inputStream.bufferedReader().use { it.readText() })
                val body = j.optString("body")
                val code = Regex("versionCode:\\s*(\\d+)").find(body)?.groupValues?.get(1)?.toLongOrNull() ?: return@thread
                val name = Regex("versionName:\\s*([0-9.]+)").find(body)?.groupValues?.get(1) ?: ""
                val url = j.optJSONArray("assets")?.optJSONObject(0)?.optString("browser_download_url") ?: return@thread
                if (code > installedCode(a)) a.runOnUiThread { if (!a.isFinishing) ask(a, name, url) }
                else if (force) a.runOnUiThread { Toast.makeText(a, "You have the latest version.", Toast.LENGTH_SHORT).show() }
            } catch (e: Exception) {
            }
        }
    }

    private fun ask(a: Activity, name: String, url: String) {
        AlertDialog.Builder(a)
            .setTitle("New version available")
            .setMessage("MyFitLevel app ${name.ifEmpty { "update" }} is ready. Tap Update to download and install it. Your login and settings stay.")
            .setPositiveButton("Update") { _, _ -> download(a, url) }
            .setNegativeButton("Later", null)
            .show()
    }

    private fun download(a: Activity, url: String) {
        if (android.os.Build.VERSION.SDK_INT >= 26 && !a.packageManager.canRequestPackageInstalls()) {
            Toast.makeText(a, "Allow MyFitLevel to install apps, then tap Update again.", Toast.LENGTH_LONG).show()
            a.startActivity(Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, Uri.parse("package:" + a.packageName)))
            return
        }
        val f = File(a.getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS), "MyFitLevel.apk")
        if (f.exists()) f.delete()
        val dm = a.getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
        val id = dm.enqueue(DownloadManager.Request(Uri.parse(url)).setTitle("MyFitLevel update")
            .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
            .setDestinationInExternalFilesDir(a, Environment.DIRECTORY_DOWNLOADS, "MyFitLevel.apk"))
        Toast.makeText(a, "Downloading update...", Toast.LENGTH_SHORT).show()
        val app = a.applicationContext
        val r = object : BroadcastReceiver() {
            override fun onReceive(c: Context, i: Intent) {
                if (i.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1) != id) return
                try { app.unregisterReceiver(this) } catch (e: Exception) {}
                val uri = FileProvider.getUriForFile(app, app.packageName + ".files", f)
                app.startActivity(Intent(Intent.ACTION_VIEW).setDataAndType(uri, "application/vnd.android.package-archive")
                    .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK))
            }
        }
        ContextCompat.registerReceiver(app, r, IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE), ContextCompat.RECEIVER_EXPORTED)
    }
}
