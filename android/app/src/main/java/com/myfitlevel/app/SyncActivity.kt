package com.myfitlevel.app

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.health.connect.client.HealthConnectClient
import androidx.health.connect.client.PermissionController
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.launch

/** Step sync status screen: shows plainly whether syncing works, and what to tap if it does not. */
class SyncActivity : AppCompatActivity() {
    private lateinit var col: LinearLayout
    private var autoAsk = false

    private val askBg = registerForActivityResult(PermissionController.createRequestPermissionResultContract()) { syncNow() }

    private val askPerms = registerForActivityResult(PermissionController.createRequestPermissionResultContract()) { granted ->
        if (StepSync.stepsPerm in granted) {
            if (StepSync.bgPerm !in granted) {
                try { askBg.launch(StepSync.bgRequest) } catch (e: Exception) { syncNow() }
            } else syncNow()
        } else showSettingsHelp()
    }

    private fun showSettingsHelp() {
        android.app.AlertDialog.Builder(this)
            .setTitle("Allow steps in Health Connect")
            .setMessage("Open the settings, tap \"Steps\" (and \"Distance\") and switch them on for MyFitLevel. Then come back here.")
            .setPositiveButton("Open settings") { _, _ -> openHealthSettings() }
            .setNegativeButton("Later", null)
            .show()
    }

    private fun openHealthSettings() {
        val tries = listOf(
            Intent("android.health.connect.action.MANAGE_HEALTH_PERMISSIONS").putExtra(Intent.EXTRA_PACKAGE_NAME, packageName),
            Intent("androidx.health.ACTION_MANAGE_HEALTH_PERMISSIONS").putExtra(Intent.EXTRA_PACKAGE_NAME, packageName),
            Intent(HealthConnectClient.ACTION_HEALTH_CONNECT_SETTINGS),
        )
        for (i in tries) { try { startActivity(i); return } catch (e: Exception) { } }
        android.widget.Toast.makeText(this, "Open Settings > Health Connect > App permissions > MyFitLevel.", android.widget.Toast.LENGTH_LONG).show()
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        title = "Step sync"
        col = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            val pad = (16 * resources.displayMetrics.density).toInt()
            setPadding(pad, pad * 2, pad, pad)
        }
        setContentView(ScrollView(this).apply { addView(col) })
        SyncWorker.schedule(this)
        autoAsk = savedInstanceState == null && intent.getBooleanExtra("auto", false)
    }

    override fun onResume() {
        super.onResume()
        render()
    }

    private fun label(t: String, size: Float = 15f, color: Int = 0xFFE6EAF0.toInt()) = TextView(this).apply {
        text = t; textSize = size; setTextColor(color); setPadding(0, 10, 0, 10)
    }

    private fun button(t: String, f: () -> Unit) = Button(this).apply { text = t; setOnClickListener { f() } }

    private fun render() {
        lifecycleScope.launch {
            val linked = Prefs.key(this@SyncActivity).isNotEmpty()
            val hcStatus = HealthConnectClient.getSdkStatus(this@SyncActivity)
            val hcOk = hcStatus == HealthConnectClient.SDK_AVAILABLE
            var stepsOk = false
            var bgOk = false
            if (hcOk) try {
                val g = HealthConnectClient.getOrCreate(this@SyncActivity).permissionController.getGrantedPermissions()
                stepsOk = StepSync.stepsPerm in g
                bgOk = StepSync.bgPerm in g
            } catch (e: Exception) { }
            val local = if (stepsOk) StepSync.todayLocal(this@SyncActivity) else null
            val lastOk = Prefs.lastOk(this@SyncActivity)
            val fresh = lastOk > 0 && System.currentTimeMillis() - lastOk < 3 * 3600_000L
            val working = linked && hcOk && stepsOk && fresh

            col.removeAllViews()
            col.addView(label("Step sync", 26f))
            val green = 0xFF4ADE80.toInt(); val red = 0xFFFB7185.toInt(); val amber = 0xFFFBBF24.toInt()
            col.addView(label(
                when {
                    working -> "✅ Working. Your steps are syncing."
                    !linked -> "❌ Not working: this phone is not linked to your account."
                    !hcOk -> "❌ Not working: Health Connect is missing or needs an update."
                    !stepsOk -> "❌ Not working: Health Connect has not been allowed to share steps."
                    lastOk == 0L -> "⚠️ Not synced yet. Tap Sync now."
                    else -> "⚠️ Last successful sync was ${Prefs.ago(lastOk)}. Tap Sync now."
                }, 18f, if (working) green else if (linked && hcOk && stepsOk) amber else red))

            fun row(ok: Boolean?, t: String) = col.addView(label((if (ok == true) "✅ " else if (ok == false) "❌ " else "➖ ") + t, 15f))
            row(linked, if (linked) "Linked to your account" else "Not linked to your account")
            row(hcOk, if (hcOk) "Health Connect installed" else "Health Connect missing / needs update")
            row(if (hcOk) stepsOk else false, if (stepsOk) "Steps access allowed" else "Steps access not allowed")
            row(if (hcOk) bgOk else null, if (bgOk) "Background sync allowed" else "Background sync not allowed (optional: syncs when you open the app)")
            row(if (lastOk > 0) fresh else false, "Last successful sync: " + Prefs.ago(lastOk))
            if (local != null) row(true, "Steps on this phone today: $local")
            col.addView(label("Last result: " + Prefs.status(this@SyncActivity), 13f, 0xFF9AA4B2.toInt()))

            if (!linked) {
                col.addView(button("Link this phone (log in first)") {
                    startActivity(Intent(this@SyncActivity, MainActivity::class.java)
                        .putExtra("url", Prefs.SITE + "/steps?connect=1")
                        .addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP))
                    finish()
                })
                col.addView(label("Or paste an API key (site: Settings > API access):", 13f, 0xFF9AA4B2.toInt()))
                val et = EditText(this@SyncActivity).apply { hint = "API key"; setTextColor(0xFFFFFFFF.toInt()); setHintTextColor(0xFF777777.toInt()) }
                col.addView(et)
                col.addView(button("Save key") {
                    val k = et.text.toString().trim()
                    if (k.length >= 20) { Prefs.setKey(this@SyncActivity, k); render() }
                    else android.widget.Toast.makeText(this@SyncActivity, "That key looks too short.", android.widget.Toast.LENGTH_SHORT).show()
                })
            } else {
                if (!stepsOk) col.addView(button("Allow Health Connect") { askPerms.launch(StepSync.toRequest) })
                if (!stepsOk) col.addView(button("Open Health Connect settings") { openHealthSettings() })
                if (!hcOk) col.addView(button("Update / install Health Connect") {
                    try { startActivity(Intent(Intent.ACTION_VIEW, android.net.Uri.parse("market://details?id=com.google.android.apps.healthdata"))) } catch (e: Exception) { }
                })
                col.addView(button("Sync now") { syncNow() })
                if (stepsOk && !bgOk) col.addView(button("Allow background sync") { try { askBg.launch(StepSync.bgRequest) } catch (e: Exception) { openHealthSettings() } })
                col.addView(button("Unlink this phone") { Prefs.setKey(this@SyncActivity, ""); render() })
            }
            col.addView(label("App v" + Updater.installedName(this@SyncActivity), 13f, 0xFF9AA4B2.toInt()))
            col.addView(button("Widgets") { startActivity(Intent(this@SyncActivity, WidgetsActivity::class.java)) })
            col.addView(button("Check for app update") { Updater.check(this@SyncActivity, true) })
            col.addView(label("The app also syncs by itself about once an hour and whenever you open it. If you miss a day, correct it by hand on the Steps page.", 13f, 0xFF9AA4B2.toInt()))

            if (autoAsk && linked && !stepsOk) { autoAsk = false; askPerms.launch(StepSync.toRequest) }
        }
    }

    private fun syncNow() {
        lifecycleScope.launch {
            Prefs.setStatus(this@SyncActivity, "Syncing...")
            render()
            Prefs.setStatus(this@SyncActivity, StepSync.sync(this@SyncActivity))
            render()
        }
    }
}
