package com.myfitlevel.app

import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.health.connect.client.PermissionController
import androidx.lifecycle.lifecycleScope
import kotlinx.coroutines.launch

class SyncActivity : AppCompatActivity() {
    private lateinit var status: TextView

    private val askBg = registerForActivityResult(PermissionController.createRequestPermissionResultContract()) {
        syncNow()
    }

    private val askPerms = registerForActivityResult(PermissionController.createRequestPermissionResultContract()) { granted ->
        if (StepSync.stepsPerm in granted) {
            if (StepSync.bgPerm !in granted) {
                try { askBg.launch(StepSync.bgRequest) } catch (e: Exception) { syncNow() }
            } else syncNow()
        } else {
            showSettingsHelp()
        }
    }

    /** The system dialog did not give access (or never showed): send the person to the right settings screen. */
    private fun showSettingsHelp() {
        android.app.AlertDialog.Builder(this)
            .setTitle("Allow steps in Health Connect")
            .setMessage("Open the settings, tap \"Steps\" (and \"Distance\") and switch them on for MyFitLevel. Then come back and tap Sync now.")
            .setPositiveButton("Open settings") { _, _ -> openHealthSettings() }
            .setNegativeButton("Later", null)
            .show()
    }

    private fun openHealthSettings() {
        val tries = listOf(
            Intent("android.health.connect.action.MANAGE_HEALTH_PERMISSIONS").putExtra(Intent.EXTRA_PACKAGE_NAME, packageName),
            Intent("androidx.health.ACTION_MANAGE_HEALTH_PERMISSIONS").putExtra(Intent.EXTRA_PACKAGE_NAME, packageName),
            Intent(androidx.health.connect.client.HealthConnectClient.ACTION_HEALTH_CONNECT_SETTINGS),
        )
        for (i in tries) {
            try { startActivity(i); return } catch (e: Exception) { }
        }
        android.widget.Toast.makeText(this, "Open Settings > Health Connect > App permissions > MyFitLevel.", android.widget.Toast.LENGTH_LONG).show()
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val pad = (16 * resources.displayMetrics.density).toInt()
        val col = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL; setPadding(pad, pad * 2, pad, pad) }
        fun label(t: String, size: Float = 15f) = TextView(this).apply { text = t; textSize = size; setTextColor(0xFFE6EAF0.toInt()); setPadding(0, pad / 2, 0, pad / 2) }

        col.addView(label("Step sync", 24f))
        col.addView(label("Sends your daily steps from Health Connect to MyFitLevel so they count for your points and team quests."))

        if (Prefs.key(this).isEmpty()) {
            col.addView(label("This phone is not linked to your account yet. Tap the button, then tap \"Connect this phone\" on the Steps page."))
            col.addView(Button(this).apply {
                text = "Link this phone"
                setOnClickListener {
                    startActivity(Intent(this@SyncActivity, MainActivity::class.java)
                        .putExtra("url", Prefs.SITE + "/steps")
                        .addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP))
                    finish()
                }
            })
            status = label("")
        } else {
            col.addView(label("Linked to your account."))
            col.addView(Button(this).apply {
                text = "Allow Health Connect"
                setOnClickListener { askPerms.launch(StepSync.toRequest) }
            })
            col.addView(Button(this).apply {
                text = "Open Health Connect settings"
                setOnClickListener { openHealthSettings() }
            })
            col.addView(Button(this).apply {
                text = "Sync now"
                setOnClickListener { syncNow() }
            })
            status = label(Prefs.status(this))
            col.addView(Button(this).apply {
                text = "Unlink this phone"
                setOnClickListener { Prefs.setKey(this@SyncActivity, ""); recreate() }
            })
        }
        col.addView(status)
        col.addView(Button(this).apply {
            text = "Widgets"
            setOnClickListener { startActivity(Intent(this@SyncActivity, WidgetsActivity::class.java)) }
        })
        col.addView(Button(this).apply {
            text = "Check for app update"
            setOnClickListener { Updater.check(this@SyncActivity, true) }
        })
        col.addView(label("The app also syncs by itself about once an hour. If you miss a day, correct it by hand on the Steps page.", 13f))
        setContentView(ScrollView(this).apply { addView(col) })
        SyncWorker.schedule(this)
        if (savedInstanceState == null && intent.getBooleanExtra("auto", false) && Prefs.key(this).isNotEmpty()) {
            askPerms.launch(StepSync.toRequest)
        }
    }

    private fun syncNow() {
        status.text = "Syncing..."
        lifecycleScope.launch {
            val s = StepSync.sync(this@SyncActivity)
            Prefs.setStatus(this@SyncActivity, s)
            status.text = s
        }
    }
}
