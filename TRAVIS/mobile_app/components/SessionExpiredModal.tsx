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
            <Ionicons name="lock-closed-outline" size={28} color="#087D78" />
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
    backgroundColor: 'rgba(15, 23, 42, 0.66)',
  },
  card: {
    width: '100%',
    maxWidth: 390,
    alignSelf: 'center',
    padding: 24,
    borderRadius: 24,
    backgroundColor: '#FFFDF7',
    borderWidth: 1,
    borderColor: 'rgba(16,47,73,.22)',
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
    backgroundColor: '#E6F5F2',
    borderWidth: 1,
    borderColor: '#B9DDDA',
    marginBottom: 18,
  },
  eyebrow: { color: '#087D78', fontSize: 10, fontWeight: '900', letterSpacing: 1.25 },
  title: { color: '#10202C', fontSize: 25, fontWeight: '900', marginTop: 7 },
  message: { color: '#526B64', fontSize: 14, lineHeight: 21, marginTop: 10, marginBottom: 24 },
  button: {
    height: 50,
    paddingHorizontal: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 9,
    borderRadius: 14,
    backgroundColor: '#087D78',
  },
  buttonPressed: { opacity: 0.86, transform: [{ scale: 0.99 }] },
  buttonText: { color: '#FFFFFF', fontSize: 14, fontWeight: '800' },
});
