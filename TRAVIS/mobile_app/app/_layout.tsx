import { Stack, useRouter } from "expo-router";
import { StatusBar } from "expo-status-bar";
import { GestureHandlerRootView } from "react-native-gesture-handler";
import React, { useEffect, useState } from "react";
import SessionExpiredModal from "../components/SessionExpiredModal";
import { registerSessionExpiredListener } from "../utils/sessionEvents";

export default function RootLayout() {
  const router = useRouter();
  const [sessionExpired, setSessionExpired] = useState(false);

  useEffect(() => registerSessionExpiredListener(() => setSessionExpired(true)), []);

  const returnToLogin = () => {
    setSessionExpired(false);
    router.replace('/(auth)/login');
  };

  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <StatusBar style="light" />

      <Stack
        initialRouteName="(auth)"
        screenOptions={{ headerShown: false }}
      >
        <Stack.Screen name="(auth)" />
        <Stack.Screen name="(drawer)" />
        <Stack.Screen name="(treasurer)" />
      </Stack>
      <SessionExpiredModal visible={sessionExpired} onSignIn={returnToLogin} />
    </GestureHandlerRootView>
  );
}
