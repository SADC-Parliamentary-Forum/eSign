import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';

class SuccessSealOverlay extends StatelessWidget {
  final VoidCallback onCompleted;

  const SuccessSealOverlay({super.key, required this.onCompleted});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.black.withOpacity(0.4),
      child: Center(
        child: Container(
          width: 200,
          height: 200,
          decoration: BoxDecoration(
            color: Colors.white,
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.2),
                blurRadius: 20,
                spreadRadius: 5,
              ),
            ],
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(
                Icons.verified,
                size: 80,
                color: Color(0xFF2F855A), // Green
              )
              .animate(onComplete: (c) => Future.delayed(const Duration(milliseconds: 500), onCompleted))
              .scale(duration: 600.ms, curve: Curves.elasticOut, begin: const Offset(0.5, 0.5)),
              const SizedBox(height: 16),
              Text(
                'SIGNED',
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w900,
                  color: Theme.of(context).colorScheme.primary, // Dynamic color used here
                  letterSpacing: 2,
                ),
              ).animate().fadeIn(delay: 200.ms).moveY(begin: 20, end: 0),
            ],
          ),
        ).animate().fadeIn(duration: 200.ms),
      ),
    );
  }
}
