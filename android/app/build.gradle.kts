plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "com.myfitlevel.app"
    compileSdk = 35
    defaultConfig {
        applicationId = "com.myfitlevel.app"
        minSdk = 26
        targetSdk = 34
        versionCode = 13
        versionName = "1.12"
    }
    signingConfigs {
        create("mfl") {
            val ks = file("mfl.jks")
            if (ks.exists()) {
                storeFile = ks
                storePassword = (System.getenv("MFL_KS_PASS") ?: "").ifBlank { "mfl-public-key" }
                keyAlias = "mfl"
                keyPassword = (System.getenv("MFL_KS_PASS") ?: "").ifBlank { "mfl-public-key" }
            }
        }
    }
    buildTypes {
        getByName("debug") {
            // A fixed signing key, so every new build can be installed over the old one.
            if (file("mfl.jks").exists()) signingConfig = signingConfigs.getByName("mfl")
        }
        release { isMinifyEnabled = false }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions { jvmTarget = "17" }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("androidx.health.connect:connect-client:1.1.0-alpha10")
    implementation("androidx.work:work-runtime-ktx:2.9.1")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.8.1")
}
