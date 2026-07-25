import { Ionicons } from '@expo/vector-icons';
import React from 'react';
import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';

type Props = {
  visible: boolean;
  onSignIn: () => void;
};

export default function SessionExpiredModal({ visible, onSignIn }: Props) {
  return (
    <Modal visible={visible} transparent animationType="fade" statusBarTranslucent onRequestClose={onSignIn}>
      <View style={styles.backdrop}>
        <View style={styles.card}>
          <View style={styles.iconWrap}>
            <Ionicons name="lock-closed-outline" size={28} color="#0F766E" />
          </View>
          <Text style={styles.eyebrow}>ACCOUNT SECURITY</Text>
          <Text style={styles.title}>Session expired</Text>
          <Text style={styles.message}>
            Your sign-in session is no longer active. Please sign in again to continue securely.
          </Text>
          <Pressable accessibilityRole="button" onPress={onSignIn} style={({ pressed }) => [styles.button, pressed && styles.buttonPressed]}>
            <Text style={styles.buttonText}>Return to sign in</Text>
            <Ionicons name="arrow-forward" size={18} color="#FFFFFF" />
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    justifyContent: 'center',
    padding: 24,
    backgroundColor: 'rgba(5, 20, 34, 0.72)',
  },
  card: {
    width: '100%',
    maxWidth: 390,
    alignSelf: 'center',
    padding: 24,
    borderRadius: 24,
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#DCE7E5',
    shadowColor: '#03131F',
    shadowOffset: { width: 0, height: 18 },
    shadowOpacity: 0.28,
    shadowRadius: 28,
    elevation: 18,
  },
  iconWrap: {
    width: 56,
    height: 56,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 18,
    backgroundColor: '#E7F5F2',
    borderWidth: 1,
    borderColor: '#B9DDD7',
    marginBottom: 18,
  },
  eyebrow: { color: '#0F766E', fontSize: 10, fontWeight: '900', letterSpacing: 1.25 },
  title: { color: '#102A3C', fontSize: 25, fontWeight: '900', marginTop: 7 },
  message: { color: '#607480', fontSize: 14, lineHeight: 21, marginTop: 10, marginBottom: 24 },
  button: {
    height: 50,
    paddingHorizontal: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 9,
    borderRadius: 14,
    backgroundColor: '#0F766E',
  },
  buttonPressed: { opacity: 0.86, transform: [{ scale: 0.99 }] },
  buttonText: { color: '#FFFFFF', fontSize: 14, fontWeight: '800' },
});
